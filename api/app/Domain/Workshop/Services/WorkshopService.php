<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\Exceptions\InvalidStatusTransition;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\AppointmentEventRecorderInterface;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\Workshop\Exceptions\NeedsReschedule;
use App\Domain\Workshop\Exceptions\NoShowBeforeStart;
use App\Domain\Workshop\Exceptions\NotServiceDay;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class WorkshopService implements WorkshopServiceInterface
{
    /** @var (\Closure(int, Appointment, list<Appointment>): void)|null */
    public static ?\Closure $beforeAttemptHook = null;

    public function __construct(
        private AppointmentTransitionInterface $transitionService,
        private AppointmentRepositoryInterface $appointmentRepository,
        private ResourceAllocationRepositoryInterface $allocationRepository,
        private AppointmentEventRecorderInterface $eventRecorder,
        private ReassignerInterface $reassigner,
        private AllocationSnapshotRepositoryInterface $allocationSnapshotRepository,
        private MasterDataRepositoryInterface $masterDataRepository,
    ) {}

    public function checkIn(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        $appointment = $this->appointmentRepository->find($appointmentId);
        if ($appointment === null) {
            throw new AppointmentNotFound($appointmentId);
        }

        // 412 beats 422: if version is stale, caller is looking at old data
        if ($appointment->version !== $expectedVersion) {
            AppointmentModifiedDetected::dispatch($appointmentId, $expectedVersion);
            throw new AppointmentModified($appointmentId, $appointment);
        }

        if (! $appointment->status->canMoveTo(AppointmentStatus::CHECKED_IN)) {
            throw new InvalidStatusTransition($appointment->status, AppointmentStatus::CHECKED_IN);
        }

        $tz = $appointment->dealership->timezone ?? 'UTC';
        $startAt = CarbonImmutable::parse($appointment->start_at);
        $appointmentDate = $startAt->setTimezone($tz)->toDateString();
        $currentDate = $now->setTimezone($tz)->toDateString();

        if ($appointmentDate !== $currentDate) {
            throw new NotServiceDay($appointmentDate, $currentDate, $tz);
        }

        if ($appointment->contact_required_reason !== null || ! $this->appointmentRepository->hasActiveAllocations($appointment->id)) {
            throw new NeedsReschedule;
        }

        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::CHECKED_IN,
            changes: [
                'checked_in_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function start(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::IN_PROGRESS,
            changes: [
                'started_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function complete(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::COMPLETED,
            changes: [
                'completed_at' => $now,
            ],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function markNoShow(User $actor, string $appointmentId, int $expectedVersion, CarbonImmutable $now): Appointment
    {
        $appointment = $this->appointmentRepository->find($appointmentId);
        if ($appointment === null) {
            throw new AppointmentNotFound($appointmentId);
        }

        // 412 beats 422: if version is stale, caller is looking at old data
        if ($appointment->version !== $expectedVersion) {
            AppointmentModifiedDetected::dispatch($appointmentId, $expectedVersion);
            throw new AppointmentModified($appointmentId, $appointment);
        }

        if (! $appointment->status->canMoveTo(AppointmentStatus::NO_SHOW)) {
            throw new InvalidStatusTransition($appointment->status, AppointmentStatus::NO_SHOW);
        }

        $startAt = CarbonImmutable::parse($appointment->start_at);
        if ($now->isBefore($startAt)) {
            throw new NoShowBeforeStart($startAt, $now);
        }

        return $this->transitionService->transition(
            id: $appointmentId,
            expectedVersion: $expectedVersion,
            to: AppointmentStatus::NO_SHOW,
            changes: [],
            outbox: null,
            actor: $actor,
            now: $now,
        );
    }

    public function extend(
        User $actor,
        string $appointmentId,
        int $expectedVersion,
        int $minutes,
        CarbonImmutable $now,
    ): ExtendResult {
        $attempt = 0;

        while (true) {
            $attempt++;

            $appointment = $this->appointmentRepository->find($appointmentId);
            if ($appointment === null) {
                throw new AppointmentNotFound($appointmentId);
            }

            // 412 beats 422: if version is stale, caller is looking at old data
            if ($appointment->version !== $expectedVersion) {
                AppointmentModifiedDetected::dispatch($appointmentId, $expectedVersion);
                throw new AppointmentModified($appointmentId, $appointment);
            }

            if (! in_array($appointment->status, [AppointmentStatus::IN_PROGRESS, AppointmentStatus::CHECKED_IN], true)) {
                throw new InvalidStatusTransition($appointment->status);
            }

            $oldEnd = CarbonImmutable::parse($appointment->end_at);
            $newEnd = $oldEnd->addMinutes($minutes);
            $extRange = TimeRange::of($oldEnd, $newEnd);

            $neighbours = $this->appointmentRepository->findActiveNeighbours(
                $appointmentId,
                (string) $appointment->technician_id,
                (string) $appointment->bay_id,
                $extRange->start,
                $extRange->end,
            );

            if (self::$beforeAttemptHook !== null) {
                (self::$beforeAttemptHook)($attempt, $appointment, $neighbours);
            }

            $dealership = $this->masterDataRepository->findDealership((string) $appointment->dealership_id);
            $tz = new DateTimeZone($dealership->timezone ?? 'UTC');

            /** @var array<string, ResourceSnapshot> $snapshots */
            $snapshots = [];
            foreach ($neighbours as $neighbour) {
                $neighbourLocalDate = CarbonImmutable::parse($neighbour->start_at)->setTimezone($tz)->toDateString();
                if (! isset($snapshots[$neighbourLocalDate])) {
                    $baseSnapshot = $this->allocationSnapshotRepository->forDay(
                        (string) $appointment->dealership_id,
                        $neighbourLocalDate,
                        $now,
                    );
                    $snapshots[$neighbourLocalDate] = $baseSnapshot
                        ->withBusy(ResourceType::TECHNICIAN, (string) $appointment->technician_id, $extRange, $appointmentId)
                        ->withBusy(ResourceType::BAY, (string) $appointment->bay_id, $extRange, $appointmentId);
                }
            }

            try {
                return DB::transaction(function () use (
                    $actor,
                    $appointment,
                    $appointmentId,
                    $expectedVersion,
                    $minutes,
                    $newEnd,
                    $neighbours,
                    $tz,
                    $now,
                    &$snapshots,
                ): ExtendResult {
                    // 1. version update on X (locks row)
                    $newVersion = $this->appointmentRepository->updateVersioned(
                        $appointmentId,
                        $expectedVersion,
                        [
                            'end_at' => $newEnd,
                        ],
                        $now,
                    );

                    // 2. process neighbours in order
                    $affected = [];
                    foreach ($neighbours as $neighbour) {
                        $neighbourLocalDate = CarbonImmutable::parse($neighbour->start_at)->setTimezone($tz)->toDateString();
                        $snapshot = $snapshots[$neighbourLocalDate];

                        $reassignmentResult = $this->reassigner->reassign(
                            $neighbour,
                            $snapshot,
                            $now,
                            $actor,
                        );

                        if ($reassignmentResult->outcome === 'REASSIGNED') {
                            $neighbourRange = TimeRange::of(
                                CarbonImmutable::parse($neighbour->start_at),
                                CarbonImmutable::parse($neighbour->end_at),
                            );
                            $snapshots[$neighbourLocalDate] = $snapshot
                                ->withBusy(ResourceType::TECHNICIAN, (string) $reassignmentResult->appointment->technician_id, $neighbourRange, $reassignmentResult->appointment->id)
                                ->withBusy(ResourceType::BAY, (string) $reassignmentResult->appointment->bay_id, $neighbourRange, $reassignmentResult->appointment->id);
                        }

                        $affected[] = $reassignmentResult->toArray();
                    }

                    // 3. set old allocations active = false
                    $this->allocationRepository->deactivateFor($appointmentId, $now);

                    // 4. insert new allocations (EXCLUDE checks them)
                    $allocations = [
                        [
                            'resource_type' => ResourceType::TECHNICIAN,
                            'resource_id' => $appointment->technician_id,
                            'start_at' => $appointment->start_at,
                            'end_at' => $newEnd,
                        ],
                        [
                            'resource_type' => ResourceType::BAY,
                            'resource_id' => $appointment->bay_id,
                            'start_at' => $appointment->start_at,
                            'end_at' => $newEnd,
                        ],
                    ];

                    if ($appointment->vehicle_id !== null) {
                        $allocations[] = [
                            'resource_type' => ResourceType::VEHICLE,
                            'resource_id' => $appointment->vehicle_id,
                            'start_at' => $appointment->start_at,
                            'end_at' => $newEnd,
                        ];
                    }

                    $this->allocationRepository->insertFor(
                        $appointmentId,
                        (string) $appointment->dealership_id,
                        $allocations,
                        $now,
                    );

                    $actorRole = null;
                    if ($actor->role !== null) {
                        $actorRole = $actor->role instanceof \BackedEnum ? $actor->role->value : (string) $actor->role;
                    }

                    // 5. record EXTENDED event
                    $this->eventRecorder->record(
                        appointmentId: $appointmentId,
                        dealershipId: (string) $appointment->dealership_id,
                        type: AppointmentEventType::EXTENDED,
                        version: $newVersion,
                        occurredAt: $now,
                        fromStatus: $appointment->status,
                        toStatus: $appointment->status,
                        actorUserId: $actor->id,
                        actorRole: $actorRole,
                        data: [
                            'minutes' => $minutes,
                        ],
                    );

                    $refreshed = $this->appointmentRepository->find($appointmentId) ?? $appointment;

                    return new ExtendResult($refreshed, $affected);
                });
            } catch (AppointmentModified $e) {
                if ($e->appointmentId === $appointmentId) {
                    throw $e;
                }

                if ($attempt < 2) {
                    continue;
                }

                throw new NoAvailability(
                    reason: 'SLOT_TAKEN',
                    requestedStart: CarbonImmutable::parse($appointment->start_at),
                    requestedEnd: $newEnd,
                    dealershipId: (string) $appointment->dealership_id,
                );
            } catch (QueryException $e) {
                if ($e->getCode() === '23P01') {
                    if ($attempt < 2) {
                        continue;
                    }

                    throw new NoAvailability(
                        reason: 'SLOT_TAKEN',
                        requestedStart: CarbonImmutable::parse($appointment->start_at),
                        requestedEnd: $newEnd,
                        dealershipId: (string) $appointment->dealership_id,
                    );
                }

                throw $e;
            } catch (NoAvailability $e) {
                if ($attempt < 2) {
                    continue;
                }

                throw $e;
            }
        }
    }
}
