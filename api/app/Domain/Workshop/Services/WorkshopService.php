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
use App\Models\Bay;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\Technician;
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

    public function board(Dealership $dealership, string $date, CarbonImmutable $now): WorkshopBoardData
    {
        $tz = new DateTimeZone($dealership->timezone ?: 'UTC');
        $fromLocal = CarbonImmutable::parse($date.' 00:00:00', $tz);
        $toLocal = $fromLocal->addDay();
        $fromUtc = $fromLocal->utc();
        $toUtc = $toLocal->utc();

        $weekday = (int) $fromLocal->isoWeekday();
        $oh = DealershipOpeningHour::query()
            ->where('dealership_id', $dealership->id)
            ->where('weekday', $weekday)
            ->first();

        $opensAt = $oh !== null ? substr((string) $oh->opens_at, 0, 5) : '08:00';
        $closesAt = $oh !== null ? substr((string) $oh->closes_at, 0, 5) : '17:00';

        $bays = Bay::query()
            ->where('dealership_id', $dealership->id)
            ->where('active', true)
            ->orderBy('code')
            ->get();

        $technicians = Technician::query()
            ->where('dealership_id', $dealership->id)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $appointments = Appointment::query()
            ->where('dealership_id', $dealership->id)
            ->where('start_at', '>=', $fromUtc)
            ->where('start_at', '<', $toUtc)
            ->with(['customer', 'serviceType', 'technician', 'bay'])
            ->orderBy('start_at', 'asc')
            ->get();

        /** @var array<string, list<array<string, mixed>>> $baysBlocks */
        $baysBlocks = [];
        /** @var list<array<string, mixed>> $displaced */
        $displaced = [];
        /** @var list<array<string, mixed>> $alerts */
        $alerts = [];
        /** @var array<string, int> $technicianBookedMinutes */
        $technicianBookedMinutes = [];
        /** @var array<string, bool> $technicianHasOverrun */
        $technicianHasOverrun = [];

        foreach ($appointments as $apt) {
            // Cancelled and no-show appointments are hidden from the board (D-12.13)
            if ($apt->status === AppointmentStatus::CANCELLED || $apt->status === AppointmentStatus::NO_SHOW) {
                continue;
            }

            // Expired holds are hidden
            if ($apt->status === AppointmentStatus::HELD && $apt->expires_at !== null) {
                if (CarbonImmutable::parse($apt->expires_at)->isBefore($now)) {
                    continue;
                }
            }

            $startLocal = CarbonImmutable::parse($apt->start_at)->setTimezone($tz);
            $endLocal = CarbonImmutable::parse($apt->end_at)->setTimezone($tz);
            $durationMinutes = (int) CarbonImmutable::parse($apt->start_at)->diffInMinutes(CarbonImmutable::parse($apt->end_at));

            // Displaced bookings (D-12.9, D-12.13)
            if ($apt->contact_required_reason !== null) {
                $displaced[] = [
                    'appointmentId' => $apt->id,
                    'reference' => $apt->reference,
                    'start' => $startLocal->toIso8601String(),
                    'end' => $endLocal->toIso8601String(),
                    'reason' => (string) $apt->contact_required_reason,
                ];

                continue;
            }

            $endUtc = CarbonImmutable::parse($apt->end_at);
            $isOverrun = $apt->status === AppointmentStatus::IN_PROGRESS && $endUtc->isBefore($now);
            $overrunMinutes = $isOverrun ? (int) $endUtc->diffInMinutes($now) : 0;

            $block = [
                'appointmentId' => $apt->id,
                'version' => $apt->version,
                'reference' => $apt->reference,
                'status' => $apt->status->value,
                'start' => $startLocal->toIso8601String(),
                'end' => $endLocal->toIso8601String(),
                'customerName' => $apt->customer !== null ? $apt->customer->full_name : '',
                'serviceName' => $apt->serviceType !== null ? $apt->serviceType->name : '',
                'technicianName' => $apt->technician !== null ? $apt->technician->name : '',
                'overrunMinutes' => $isOverrun ? $overrunMinutes : 0,
            ];

            if ($apt->bay_id !== '') {
                $baysBlocks[$apt->bay_id][] = $block;
            }

            if ($isOverrun) {
                $nextBooking = $this->findNextBooking($dealership->id, $apt, $toUtc, $tz);

                $alerts[] = [
                    'type' => 'OVERRUN',
                    'appointmentId' => $apt->id,
                    'version' => $apt->version,
                    'bayName' => $apt->bay !== null ? $apt->bay->name : '',
                    'minutesOver' => $overrunMinutes,
                    'customerName' => $apt->customer !== null ? $apt->customer->full_name : '',
                    'serviceName' => $apt->serviceType !== null ? $apt->serviceType->name : '',
                    'technicianName' => $apt->technician !== null ? $apt->technician->name : '',
                    'nextBooking' => $nextBooking,
                ];
            }

            if ($apt->technician_id !== '') {
                $techId = $apt->technician_id;
                $technicianBookedMinutes[$techId] = ($technicianBookedMinutes[$techId] ?? 0) + $durationMinutes;
                if ($isOverrun) {
                    $technicianHasOverrun[$techId] = true;
                }
            }
        }

        $bayList = [];
        foreach ($bays as $bay) {
            $bayList[] = [
                'id' => $bay->id,
                'code' => $bay->code,
                'name' => $bay->name,
                'bayType' => $bay->bay_type->value,
                'blocks' => $baysBlocks[$bay->id] ?? [],
            ];
        }

        $techList = [];
        foreach ($technicians as $tech) {
            $techId = (string) $tech->id;
            $hasOverrun = $technicianHasOverrun[$techId] ?? false;

            $techList[] = [
                'id' => $tech->id,
                'name' => $tech->name,
                'level' => $tech->level,
                'bookedMinutes' => $technicianBookedMinutes[$techId] ?? 0,
                'status' => $hasOverrun ? 'OVERRUN' : 'WORKING',
            ];
        }

        return new WorkshopBoardData(
            date: $date,
            now: $now->setTimezone($tz)->toIso8601String(),
            opensAt: $opensAt,
            closesAt: $closesAt,
            bays: $bayList,
            displaced: $displaced,
            technicians: $techList,
            alerts: $alerts,
        );
    }

    /**
     * @return array{appointmentId: string, start: string, status: string}|null
     */
    private function findNextBooking(string $dealershipId, Appointment $current, CarbonImmutable $toUtc, DateTimeZone $tz): ?array
    {
        /** @var Appointment|null $next */
        $next = Appointment::query()
            ->where('dealership_id', $dealershipId)
            ->where('id', '!=', $current->id)
            ->where(function ($query) use ($current) {
                $query->where('bay_id', $current->bay_id);
                if ($current->technician_id !== '') {
                    $query->orWhere('technician_id', $current->technician_id);
                }
            })
            ->where('start_at', '>=', $current->end_at)
            ->where('start_at', '<', $toUtc)
            ->orderBy('start_at', 'asc')
            ->first();

        if ($next === null) {
            return null;
        }

        return [
            'appointmentId' => $next->id,
            'start' => CarbonImmutable::parse($next->start_at)->setTimezone($tz)->toIso8601String(),
            'status' => $next->status->value,
        ];
    }
}
