<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\OpeningHours;
use App\Domain\Allocation\TimeRange;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Role;
use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\BookingTimeRules;
use App\Domain\Booking\CancelReason;
use App\Domain\Booking\CreateBookingInput;
use App\Domain\Booking\CustomerChangePolicy;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\CustomerNotFound;
use App\Domain\Booking\Exceptions\HoldExpired;
use App\Domain\Booking\Exceptions\HoldNotAllowed;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Exceptions\RescheduleSameTime;
use App\Domain\Booking\Exceptions\TooLateToCancel;
use App\Domain\Booking\Exceptions\TooLateToReschedule;
use App\Domain\Booking\Exceptions\VehicleAlreadyBooked;
use App\Domain\Booking\Exceptions\VehicleNotFound;
use App\Domain\Booking\Exceptions\VehicleNotOwnedByCustomer;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\Repositories\VehicleRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Domain\MasterData\Exceptions\DealershipNotFound;
use App\Domain\MasterData\Exceptions\ServiceTypeNotFound;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Services\OutboxWriterInterface;
use App\Models\Appointment;
use App\Models\User;
use App\Support\Tracing\Tracer;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final readonly class BookingService implements BookingServiceInterface
{
    private AppointmentRepositoryInterface $appointmentRepository;

    private AppointmentTransitionInterface $appointmentTransition;

    private ResourceAllocationRepositoryInterface $allocationRepository;

    private AppointmentEventRecorderInterface $eventRecorder;

    private OutboxWriterInterface $outboxWriter;

    private Tracer $tracer;

    private ExpiredHoldReleaserInterface $expiredHoldReleaser;

    public function __construct(
        private MasterDataRepositoryInterface $masterDataRepository,
        private CustomerRepositoryInterface $customerRepository,
        private VehicleRepositoryInterface $vehicleRepository,
        private AllocationSnapshotRepositoryInterface $snapshotRepository,
        private CandidateFinderInterface $candidateFinder,
        private AppointmentWriterInterface $appointmentWriter,
        private ?AvailabilityServiceInterface $availabilityService = null,
        ?AppointmentRepositoryInterface $appointmentRepository = null,
        ?AppointmentTransitionInterface $appointmentTransition = null,
        ?ResourceAllocationRepositoryInterface $allocationRepository = null,
        ?AppointmentEventRecorderInterface $eventRecorder = null,
        ?OutboxWriterInterface $outboxWriter = null,
        ?Tracer $tracer = null,
        ?ExpiredHoldReleaserInterface $expiredHoldReleaser = null,
    ) {
        $this->appointmentRepository = $appointmentRepository ?? app(AppointmentRepositoryInterface::class);
        $this->appointmentTransition = $appointmentTransition ?? app(AppointmentTransitionInterface::class);
        $this->allocationRepository = $allocationRepository ?? app(ResourceAllocationRepositoryInterface::class);
        $this->eventRecorder = $eventRecorder ?? app(AppointmentEventRecorderInterface::class);
        $this->outboxWriter = $outboxWriter ?? app(OutboxWriterInterface::class);
        $this->tracer = $tracer ?? app(Tracer::class);
        $this->expiredHoldReleaser = $expiredHoldReleaser ?? app(ExpiredHoldReleaserInterface::class);
    }

    public function create(CreateBookingInput $input, User $actor): Appointment
    {
        $role = $actor->role instanceof Role ? $actor->role : Role::tryFrom((string) $actor->role);
        $isCustomer = $role === Role::CUSTOMER;

        if ($input->mode === 'HOLD' && ! $isCustomer) {
            throw new HoldNotAllowed;
        }

        [$dealership, $serviceType, $customer, $vehicle, $range, $now] = $this->tracer->span('booking.validate', function () use ($input, $actor, $isCustomer) {
            // 1. Dealership
            $dealershipId = $isCustomer ? $input->dealershipId : $actor->dealership_id;
            if ($dealershipId === null) {
                throw new DealershipNotFound;
            }

            $dealership = $this->masterDataRepository->findDealership($dealershipId);
            if ($dealership === null) {
                throw new DealershipNotFound;
            }

            // 2. Service type
            $serviceType = $this->masterDataRepository->serviceTypeFor($dealershipId, $input->serviceTypeId);
            if ($serviceType === null || ! $serviceType->active) {
                throw new ServiceTypeNotFound;
            }

            // 3. Customer
            if ($isCustomer) {
                $customer = $actor->customer;
                if ($customer === null) {
                    throw new CustomerNotFound;
                }
            } else {
                if ($input->customerId === null) {
                    throw new CustomerNotFound;
                }
                $customer = $this->customerRepository->find($input->customerId);
                if ($customer === null) {
                    throw new CustomerNotFound;
                }
            }

            // 4. Vehicle
            $vehicle = null;
            if ($input->vehicleId !== null) {
                $vehicle = $this->vehicleRepository->find($input->vehicleId);
                if ($vehicle === null) {
                    throw new VehicleNotFound;
                }

                if ($vehicle->customer_id !== $customer->id) {
                    if ($isCustomer) {
                        throw new VehicleNotFound;
                    }
                    throw new VehicleNotOwnedByCustomer;
                }
            } elseif ($input->mode !== 'HOLD') {
                throw new VehicleNotFound;
            }

            // 5. BookingTimeRules
            $now = CarbonImmutable::now();
            $openingHoursList = $this->masterDataRepository->openingHours($dealershipId);
            $openingHoursMap = [];
            foreach ($openingHoursList as $oh) {
                $openingHoursMap[$oh->weekday] = [
                    'opens_at' => $oh->opens_at,
                    'closes_at' => $oh->closes_at,
                ];
            }

            $openingHours = OpeningHours::fromWeekdayMap($openingHoursMap, $dealership->timezone);
            $range = BookingTimeRules::check($input->desiredStart, $serviceType->duration_minutes, $openingHours, $now);

            return [$dealership, $serviceType, $customer, $vehicle, $range, $now];
        });

        $this->expiredHoldReleaser->releaseBlocking(
            dealershipId: $dealership->id,
            range: $range,
            vehicleId: $vehicle?->id,
            now: $now,
        );

        // 6. Candidate finder
        $dealershipId = $dealership->id;
        $tz = new DateTimeZone($dealership->timezone);
        $localDate = $range->start->setTimezone($tz)->toDateString();
        $snapshot = $this->snapshotRepository->forDay($dealershipId, $localDate, $now);

        $allocationRequest = new AllocationRequest(
            dealershipId: $dealershipId,
            requiredLevel: $serviceType->requiredLevel(),
            requiredBayType: $serviceType->required_bay_type,
            range: $range,
        );

        $result = $this->tracer->span('allocation.find_candidates', function () use ($allocationRequest, $snapshot) {
            return $this->candidateFinder->find($allocationRequest, $snapshot);
        });

        try {
            if (empty($result->pairs)) {
                throw new NoAvailability(
                    reason: $result->failure !== null ? $result->failure->value : 'SLOT_TAKEN',
                    requestedStart: $range->start->setTimezone($tz),
                    requestedEnd: $range->end->setTimezone($tz),
                    dealershipId: $dealershipId,
                );
            }

            // 7. Write appointment
            $source = $isCustomer ? 'CUSTOMER' : 'STAFF';
            $isHold = $input->mode === 'HOLD';
            $status = $isHold ? AppointmentStatus::HELD : AppointmentStatus::CONFIRMED;
            $holdMinutes = (int) (config('app.hold_minutes') ?? 5);
            $expiresAt = $isHold ? $now->addMinutes($holdMinutes) : null;

            $newAppointment = new NewAppointment(
                dealershipId: $dealershipId,
                customerId: $customer->id,
                vehicleId: $vehicle?->id,
                serviceTypeId: $serviceType->id,
                range: $range,
                status: $status,
                expiresAt: $expiresAt,
                reminderOptIn: $input->reminderOptIn,
                source: $source,
                createdByUserId: $actor->id,
            );

            return $this->tracer->span('booking.write', function () use (
                $isHold,
                $customer,
                $actor,
                $now,
                $newAppointment,
                $result,
            ) {
                if (! $isHold) {
                    return $this->appointmentWriter->create($newAppointment, $result->pairs, $now);
                }

                return DB::transaction(function () use (
                    $customer,
                    $actor,
                    $now,
                    $newAppointment,
                    $result,
                ): Appointment {
                    // Lock customer row FOR UPDATE to serialise holds for this customer (D-10.4)
                    DB::table('customers')->where('id', $customer->id)->lockForUpdate()->first();

                    // Read existing HELD appointment for this customer after acquiring the lock
                    /** @var object{id: string, version: int}|null $existingHold */
                    $existingHold = DB::table('appointments')
                        ->where('customer_id', $customer->id)
                        ->where('status', AppointmentStatus::HELD->value)
                        ->lockForUpdate()
                        ->first();

                    if ($existingHold !== null) {
                        try {
                            $this->appointmentTransition->transition(
                                id: (string) $existingHold->id,
                                expectedVersion: (int) $existingHold->version,
                                to: AppointmentStatus::EXPIRED,
                                changes: [],
                                outbox: null,
                                actor: $actor,
                                now: $now,
                            );
                        } catch (AppointmentModified|InvalidStatusTransition) {
                            // Concurrently modified or expired: ignore since it is no longer HELD
                        }
                    }

                    return $this->appointmentWriter->create($newAppointment, $result->pairs, $now);
                });
            });
        } catch (NoAvailability $e) {
            $availability = $this->availabilityService ?? app(AvailabilityServiceInterface::class);
            $suggestions = $availability->near(
                dealershipId: $dealershipId,
                type: $serviceType,
                wanted: $range->start->setTimezone($tz),
                now: $now,
                actor: $actor,
            );

            $formattedAlternatives = array_map(fn (TimeRange $r) => [
                'start' => $r->start->setTimezone($tz)->toIso8601String(),
                'end' => $r->end->setTimezone($tz)->toIso8601String(),
            ], $suggestions);

            throw new NoAvailability(
                reason: $e->reason,
                requestedStart: $e->requestedStart->setTimezone($tz),
                requestedEnd: $e->requestedEnd->setTimezone($tz),
                dealershipId: $e->dealershipId,
                detail: $e->detail,
                alternatives: $formattedAlternatives,
            );
        }
    }

    public function cancel(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        ?CancelReason $reason,
        ?string $note,
        CarbonImmutable $now,
    ): Appointment {
        $appointment = $this->appointmentRepository->findVisibleTo($actor, $appointmentId);

        // D-07.4: Cancelling a CANCELLED booking returns 200 with current state, nothing written
        if ($appointment->status === AppointmentStatus::CANCELLED) {
            return $appointment;
        }

        $role = $actor->role instanceof Role ? $actor->role : Role::tryFrom((string) $actor->role);
        $isCustomer = $role === Role::CUSTOMER;

        // Customer time check: CONFIRMED requires start - now >= 2 hours (INV-07.3, BR-07.2)
        if ($isCustomer && $appointment->status === AppointmentStatus::CONFIRMED) {
            if (! CustomerChangePolicy::isWithinCustomerChangeWindow($appointment, $now)) {
                $dealership = $appointment->dealership ?? $this->masterDataRepository->findDealership($appointment->dealership_id);
                $phone = $dealership !== null ? $dealership->phone : '';
                throw new TooLateToCancel(
                    dealershipPhone: $phone,
                    start: CarbonImmutable::parse($appointment->start_at)->toIso8601String(),
                );
            }
        }

        $changes = [
            'cancel_reason' => $reason?->value,
            'cancel_note' => $note !== null ? trim($note) : null,
            'cancelled_at' => $now,
            'cancelled_by_user_id' => $actor->id,
        ];

        $outbox = $appointment->status === AppointmentStatus::CONFIRMED
            ? OutboxEventType::AppointmentCancelled
            : null;

        try {
            return $this->appointmentTransition->transition(
                id: $appointment->id,
                expectedVersion: $expectedVersion,
                to: AppointmentStatus::CANCELLED,
                changes: $changes,
                outbox: $outbox,
                actor: $actor,
                now: $now,
            );
        } catch (AppointmentModified $e) {
            // D-07.5: if versioned update hit 0 rows or version mismatch, but fresh read is CANCELLED -> 200
            $fresh = $this->appointmentRepository->find($appointmentId);
            if ($fresh !== null && $fresh->status === AppointmentStatus::CANCELLED) {
                return $fresh;
            }
            throw $e;
        }
    }

    public function reschedule(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        CarbonImmutable $newStart,
        bool $keepTechnician,
        CarbonImmutable $now,
    ): Appointment {
        $appointment = $this->appointmentRepository->findVisibleTo($actor, $appointmentId);

        // 2. Status check & 412 precedence (RQ §B7 step 2, INV-09.5)
        if ($appointment->version !== $expectedVersion) {
            throw new AppointmentModified($appointment->id, $appointment);
        }

        if ($appointment->status !== AppointmentStatus::CONFIRMED) {
            throw new InvalidStatusTransition($appointment->status);
        }

        $role = $actor->role instanceof Role ? $actor->role : Role::tryFrom((string) $actor->role);
        $isCustomer = $role === Role::CUSTOMER;

        // 3. Customer 2-hour change window (RQ §B7 step 3, INV-09.6)
        if ($isCustomer) {
            if (! CustomerChangePolicy::isWithinCustomerChangeWindow($appointment, $now)) {
                $dealership = $appointment->dealership ?? $this->masterDataRepository->findDealership($appointment->dealership_id);
                $phone = $dealership !== null ? $dealership->phone : '';
                throw new TooLateToReschedule(
                    dealershipPhone: $phone,
                    start: CarbonImmutable::parse($appointment->start_at)->toIso8601String(),
                );
            }
        }

        // 4. Same time check (RQ §B7 step 4, D-09.5)
        $currentStart = CarbonImmutable::parse($appointment->start_at);
        if ($newStart->equalTo($currentStart)) {
            $hasActiveAllocations = $this->appointmentRepository->hasActiveAllocations($appointment->id);
            if ($hasActiveAllocations) {
                throw new RescheduleSameTime;
            }
        }

        // 5. Booking time rules (RQ §B7 step 5, INV-09.7)
        $dealership = $appointment->dealership ?? $this->masterDataRepository->findDealership($appointment->dealership_id);
        if ($dealership === null) {
            throw new DealershipNotFound;
        }

        $serviceType = $appointment->serviceType ?? $this->masterDataRepository->serviceTypeFor($dealership->id, $appointment->service_type_id);
        if ($serviceType === null) {
            throw new ServiceTypeNotFound;
        }

        $openingHoursList = $this->masterDataRepository->openingHours($dealership->id);
        $openingHoursMap = [];
        foreach ($openingHoursList as $oh) {
            $openingHoursMap[$oh->weekday] = [
                'opens_at' => $oh->opens_at,
                'closes_at' => $oh->closes_at,
            ];
        }

        $openingHours = OpeningHours::fromWeekdayMap($openingHoursMap, $dealership->timezone);
        $newRange = BookingTimeRules::check($newStart, $serviceType->duration_minutes, $openingHours, $now);

        $dealershipId = $dealership->id;

        $this->expiredHoldReleaser->releaseBlocking(
            dealershipId: $dealershipId,
            range: $newRange,
            vehicleId: $appointment->vehicle_id,
            now: $now,
        );

        // 6. Candidate search (RQ §B7 step 6, INV-09.4)
        $tz = new DateTimeZone($dealership->timezone);
        $localDate = $newRange->start->setTimezone($tz)->toDateString();
        $snapshot = $this->snapshotRepository->forDay($dealershipId, $localDate, $now);

        $effectiveKeepTechnician = $isCustomer ? true : $keepTechnician;

        $allocationRequest = new AllocationRequest(
            dealershipId: $dealershipId,
            requiredLevel: $serviceType->requiredLevel(),
            requiredBayType: $serviceType->required_bay_type,
            range: $newRange,
            preferTechnicianId: $effectiveKeepTechnician ? $appointment->technician_id : null,
            preferBayId: $appointment->bay_id,
            excludeAppointmentId: $appointment->id,
        );

        $result = $this->tracer->span('allocation.find_candidates', function () use ($allocationRequest, $snapshot) {
            return $this->candidateFinder->find($allocationRequest, $snapshot);
        });

        // 7. Atomic move via AppointmentWriter::moveTo (RQ §B7 step 7, INV-09.1)
        try {
            if (empty($result->pairs)) {
                throw new NoAvailability(
                    reason: $result->failure !== null ? $result->failure->value : 'SLOT_TAKEN',
                    requestedStart: $newRange->start->setTimezone($tz),
                    requestedEnd: $newRange->end->setTimezone($tz),
                    dealershipId: $dealershipId,
                );
            }

            return $this->tracer->span('booking.move', function () use ($appointment, $expectedVersion, $newRange, $result, $now, $actor) {
                return $this->appointmentWriter->moveTo(
                    appointmentId: $appointment->id,
                    expectedVersion: $expectedVersion,
                    newRange: $newRange,
                    pairs: $result->pairs,
                    event: AppointmentEventType::RESCHEDULED,
                    outbox: OutboxEventType::AppointmentRescheduled,
                    now: $now,
                    actor: $actor,
                );
            });
        } catch (NoAvailability $e) {
            $availability = $this->availabilityService ?? app(AvailabilityServiceInterface::class);
            $suggestions = $availability->near(
                dealershipId: $dealershipId,
                type: $serviceType,
                wanted: $newRange->start->setTimezone($tz),
                now: $now,
                excludeAppointmentId: $appointment->id,
                actor: $actor,
            );

            $formattedAlternatives = array_map(fn (TimeRange $r) => [
                'start' => $r->start->setTimezone($tz)->toIso8601String(),
                'end' => $r->end->setTimezone($tz)->toIso8601String(),
            ], $suggestions);

            throw new NoAvailability(
                reason: $e->reason,
                requestedStart: $e->requestedStart->setTimezone($tz),
                requestedEnd: $e->requestedEnd->setTimezone($tz),
                dealershipId: $e->dealershipId,
                detail: $e->detail,
                alternatives: $formattedAlternatives,
            );
        }
    }

    public function confirmHold(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        string $vehicleId,
        bool $reminderOptIn,
        CarbonImmutable $now,
    ): Appointment {
        $appointment = $this->appointmentRepository->findVisibleTo($actor, $appointmentId);

        // 2. status ≠ HELD: version ≠ expected → 412; else 422 INVALID_STATUS_TRANSITION.
        if ($appointment->status !== AppointmentStatus::HELD) {
            if ($appointment->version !== $expectedVersion) {
                AppointmentModifiedDetected::dispatch($appointmentId, $expectedVersion);
                throw new AppointmentModified($appointmentId, $appointment);
            }
            throw new InvalidStatusTransition($appointment->status, AppointmentStatus::CONFIRMED);
        }

        // 3. expires_at <= now → TransitionAppointment(EXPIRED, expected); success or AppointmentModified with status EXPIRED → 410 HOLD_EXPIRED; other 412 → rethrow.
        if ($appointment->expires_at !== null && CarbonImmutable::parse($appointment->expires_at)->lessThanOrEqualTo($now)) {
            try {
                $this->appointmentTransition->transition(
                    id: $appointmentId,
                    expectedVersion: $expectedVersion,
                    to: AppointmentStatus::EXPIRED,
                    changes: [],
                    outbox: null,
                    actor: null,
                    now: $now,
                );
                throw new HoldExpired;
            } catch (AppointmentModified $e) {
                $latest = $this->appointmentRepository->find($appointmentId);
                if ($latest?->status === AppointmentStatus::EXPIRED) {
                    throw new HoldExpired;
                }
                throw $e;
            }
        }

        // 4. Vehicle must belong to the customer → else 404 VEHICLE_NOT_FOUND.
        $customer = $actor->customer ?? $this->customerRepository->findByUserId($actor->id);
        $vehicle = $this->vehicleRepository->find($vehicleId);
        if ($vehicle === null || $customer === null || $vehicle->customer_id !== $customer->id) {
            throw new VehicleNotFound;
        }

        // 5. Transaction: updateVersioned(status CONFIRMED, vehicle_id, reminder_opt_in, expires_at NULL) (0 rows → 412)
        // -> if the vehicle allocation is missing or for another vehicle: deactivate it and insert VEHICLE with the same period
        // (23P01 → rollback, 409 VEHICLE_ALREADY_BOOKED) → history CONFIRMED → outbox AppointmentConfirmed → commit.
        try {
            return DB::transaction(function () use (
                $appointment,
                $expectedVersion,
                $vehicle,
                $reminderOptIn,
                $now,
                $actor,
            ): Appointment {
                $newVersion = $this->appointmentRepository->updateVersioned(
                    id: $appointment->id,
                    expectedVersion: $expectedVersion,
                    changes: [
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'vehicle_id' => $vehicle->id,
                        'reminder_opt_in' => $reminderOptIn,
                        'expires_at' => null,
                    ],
                    now: $now,
                );

                $existingVehicleAlloc = DB::table('resource_allocations')
                    ->where('appointment_id', $appointment->id)
                    ->where('resource_type', ResourceType::VEHICLE->value)
                    ->where('active', true)
                    ->first();

                if ($existingVehicleAlloc === null || $existingVehicleAlloc->resource_id !== $vehicle->id) {
                    if ($existingVehicleAlloc !== null) {
                        $this->allocationRepository->deactivateType($appointment->id, ResourceType::VEHICLE, $now);
                    }

                    $this->allocationRepository->insertFor(
                        $appointment->id,
                        (string) $appointment->dealership_id,
                        [
                            [
                                'resource_type' => ResourceType::VEHICLE,
                                'resource_id' => $vehicle->id,
                                'start_at' => $appointment->start_at,
                                'end_at' => $appointment->end_at,
                            ],
                        ],
                        $now,
                    );
                }

                $actorRole = $actor->role instanceof Role ? $actor->role->value : ($actor->role ? (string) $actor->role : 'CUSTOMER');

                $this->eventRecorder->record(
                    appointmentId: $appointment->id,
                    dealershipId: (string) $appointment->dealership_id,
                    type: AppointmentEventType::CONFIRMED,
                    version: $newVersion,
                    occurredAt: $now,
                    fromStatus: AppointmentStatus::HELD,
                    toStatus: AppointmentStatus::CONFIRMED,
                    actorUserId: $actor->id,
                    actorRole: $actorRole,
                    data: [
                        'vehicleId' => $vehicle->id,
                        'reminderOptIn' => $reminderOptIn,
                    ],
                );

                $this->outboxWriter->append(
                    dealershipId: (string) $appointment->dealership_id,
                    appointmentId: $appointment->id,
                    eventType: OutboxEventType::AppointmentConfirmed,
                    payload: [
                        'appointmentId' => $appointment->id,
                        'reference' => $appointment->reference,
                        'customerId' => $appointment->customer_id,
                        'vehicleId' => $vehicle->id,
                        'serviceTypeId' => $appointment->service_type_id,
                        'start' => CarbonImmutable::parse($appointment->start_at)->toIso8601String(),
                        'end' => CarbonImmutable::parse($appointment->end_at)->toIso8601String(),
                    ],
                    now: $now,
                );

                return $this->appointmentRepository->find($appointment->id) ?? throw new AppointmentNotFound($appointment->id);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23P01') {
                $clashType = ExclusionViolationParser::parse($e);
                if ($clashType === ResourceType::VEHICLE) {
                    $conflicting = DB::table('resource_allocations')
                        ->where('resource_type', ResourceType::VEHICLE->value)
                        ->where('resource_id', $vehicle->id)
                        ->where('active', true)
                        ->whereRaw('period && tstzrange(?, ?, \'[)\')', [
                            CarbonImmutable::parse($appointment->start_at)->toIso8601String(),
                            CarbonImmutable::parse($appointment->end_at)->toIso8601String(),
                        ])
                        ->first();

                    $existing = null;
                    if ($conflicting !== null) {
                        $existing = ['id' => (string) $conflicting->appointment_id];
                        $conflictingAppt = DB::table('appointments')->where('id', $conflicting->appointment_id)->first();
                        if ($conflictingAppt !== null) {
                            $existing['reference'] = (string) $conflictingAppt->reference;
                            $existing['start'] = (string) $conflictingAppt->start_at;
                            $existing['end'] = (string) $conflictingAppt->end_at;
                            $existing['status'] = (string) $conflictingAppt->status;
                        }
                    }

                    throw new VehicleAlreadyBooked($existing);
                }
            }
            throw $e;
        }
    }
}
