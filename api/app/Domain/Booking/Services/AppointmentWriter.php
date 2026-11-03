<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\CandidatePair;
use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AllocationRetried;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Exceptions\VehicleAlreadyBooked;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Services\OutboxWriter;
use App\Domain\Notifications\Services\OutboxWriterInterface;
use App\Models\Appointment;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AppointmentWriter implements AppointmentWriterInterface
{
    private AppointmentEventRecorderInterface $eventRecorder;

    private OutboxWriterInterface $outboxWriter;

    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        ?AppointmentEventRecorderInterface $eventRecorder = null,
        ?OutboxWriterInterface $outboxWriter = null,
    ) {
        $this->eventRecorder = $eventRecorder ?? new AppointmentEventRecorder;
        $this->outboxWriter = $outboxWriter ?? new OutboxWriter;
    }

    /**
     * @param  list<CandidatePair>  $pairs
     */
    public function create(NewAppointment $data, array $pairs, CarbonImmutable $now): Appointment
    {
        if (empty($pairs)) {
            throw new NoAvailability(
                reason: 'SLOT_TAKEN',
                requestedStart: $data->range->start,
                requestedEnd: $data->range->end,
                dealershipId: $data->dealershipId,
            );
        }

        $dealership = Dealership::findOrFail($data->dealershipId);
        $tz = new DateTimeZone($dealership->timezone);
        $startInLocalTz = $data->range->start->setTimezone($tz);

        /** @var list<object{seq: int}> $seqResult */
        $seqResult = DB::select("SELECT nextval('appointment_reference_seq') AS seq");
        $seq = $seqResult[0]->seq;
        $reference = sprintf('APT-%s-%04d', $startInLocalTz->format('Ymd'), $seq);

        $tries = 0;
        $remainingPairs = $pairs;

        while (! empty($remainingPairs) && $tries < 3) {
            $tries++;
            /** @var CandidatePair $pair */
            $pair = array_shift($remainingPairs);
            $technicianId = $pair->technician->id;
            $bayId = $pair->bay->id;

            try {
                $appointment = DB::transaction(function () use ($data, $reference, $technicianId, $bayId, $now): Appointment {
                    $techRow = DB::table('technicians')
                        ->where('id', $technicianId)
                        ->sharedLock()
                        ->first();

                    if ($techRow === null || ! $techRow->active) {
                        throw new TechnicianInactiveException;
                    }

                    $appointmentId = (string) Str::uuid();

                    $attributes = [
                        'id' => $appointmentId,
                        'reference' => $reference,
                        'dealership_id' => $data->dealershipId,
                        'customer_id' => $data->customerId,
                        'vehicle_id' => $data->vehicleId,
                        'service_type_id' => $data->serviceTypeId,
                        'technician_id' => $technicianId,
                        'bay_id' => $bayId,
                        'status' => $data->status->value,
                        'start_at' => $data->range->start,
                        'end_at' => $data->range->end,
                        'expires_at' => $data->expiresAt,
                        'version' => 1,
                        'source' => $data->source,
                        'reminder_opt_in' => $data->reminderOptIn,
                        'created_by_user_id' => $data->createdByUserId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];

                    $created = $this->appointmentRepository->insert($attributes);

                    $allocations = [
                        [
                            'resource_type' => ResourceType::TECHNICIAN,
                            'resource_id' => $technicianId,
                            'start_at' => $data->range->start,
                            'end_at' => $data->range->end,
                        ],
                        [
                            'resource_type' => ResourceType::BAY,
                            'resource_id' => $bayId,
                            'start_at' => $data->range->start,
                            'end_at' => $data->range->end,
                        ],
                    ];

                    if ($data->vehicleId !== null) {
                        $allocations[] = [
                            'resource_type' => ResourceType::VEHICLE,
                            'resource_id' => $data->vehicleId,
                            'start_at' => $data->range->start,
                            'end_at' => $data->range->end,
                        ];
                    }

                    $this->allocationRepository->insertFor($appointmentId, $data->dealershipId, $allocations, $now);

                    $this->eventRecorder->record(
                        appointmentId: $appointmentId,
                        dealershipId: $data->dealershipId,
                        type: AppointmentEventType::CREATED,
                        version: 1,
                        occurredAt: $now,
                        fromStatus: null,
                        toStatus: $data->status,
                        actorUserId: $data->createdByUserId,
                        actorRole: null,
                        data: [],
                    );

                    if ($data->status === AppointmentStatus::CONFIRMED) {
                        $this->outboxWriter->append(
                            dealershipId: $data->dealershipId,
                            appointmentId: $appointmentId,
                            eventType: OutboxEventType::AppointmentConfirmed,
                            payload: [
                                'appointmentId' => $appointmentId,
                                'reference' => $reference,
                                'customerId' => $data->customerId,
                                'vehicleId' => $data->vehicleId,
                                'serviceTypeId' => $data->serviceTypeId,
                                'start' => $data->range->start->toIso8601String(),
                                'end' => $data->range->end->toIso8601String(),
                            ],
                            now: $now,
                        );
                    }

                    return $created;
                });

                return $this->appointmentRepository->find((string) $appointment->id) ?? $appointment;
            } catch (QueryException $e) {
                if ($e->getCode() === '23P01') {
                    $clashType = ExclusionViolationParser::parse($e);

                    if ($clashType === ResourceType::VEHICLE) {
                        $conflicting = null;
                        if ($data->vehicleId !== null) {
                            $conflicting = DB::table('resource_allocations')
                                ->where('resource_type', 'VEHICLE')
                                ->where('resource_id', $data->vehicleId)
                                ->where('active', true)
                                ->whereRaw('period && tstzrange(?, ?, \'[)\')', [
                                    $data->range->start->toIso8601String(),
                                    $data->range->end->toIso8601String(),
                                ])
                                ->first();
                        }

                        $existing = null;
                        if ($conflicting !== null) {
                            $existing = ['id' => (string) $conflicting->appointment_id];
                            $conflictingAppt = DB::table('appointments')->where('id', $conflicting->appointment_id)->first();
                            if ($conflictingAppt !== null) {
                                $existing['reference'] = (string) $conflictingAppt->reference;
                                $existing['start'] = (string) $conflictingAppt->start_at;
                                $existing['end'] = (string) $conflictingAppt->end_at;
                            }
                        }

                        throw new VehicleAlreadyBooked($existing);
                    }

                    if ($clashType === ResourceType::BAY) {
                        $remainingPairs = array_values(array_filter(
                            $remainingPairs,
                            fn (CandidatePair $p): bool => $p->bay->id !== $bayId
                        ));
                        AllocationRetried::dispatch($data->dealershipId, ResourceType::BAY, $bayId);

                        continue;
                    }

                    $remainingPairs = array_values(array_filter(
                        $remainingPairs,
                        fn (CandidatePair $p): bool => $p->technician->id !== $technicianId
                    ));
                    AllocationRetried::dispatch($data->dealershipId, ResourceType::TECHNICIAN, $technicianId);

                    continue;
                }

                if ($e->getCode() === '40P01') {
                    // Deadlock detected during concurrent exclusion constraint checking.
                    // Sleep with small jitter and retry.
                    usleep(random_int(10000, 30000));

                    continue;
                }

                throw $e;
            } catch (TechnicianInactiveException) {
                $remainingPairs = array_values(array_filter(
                    $remainingPairs,
                    fn (CandidatePair $p): bool => $p->technician->id !== $technicianId
                ));
                AllocationRetried::dispatch($data->dealershipId, ResourceType::TECHNICIAN, $technicianId);

                continue;
            }
        }

        throw new NoAvailability(
            reason: 'SLOT_TAKEN',
            requestedStart: $data->range->start,
            requestedEnd: $data->range->end,
            dealershipId: $data->dealershipId,
        );
    }
}

class TechnicianInactiveException extends RuntimeException {}
