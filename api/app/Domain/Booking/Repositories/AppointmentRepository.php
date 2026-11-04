<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\TimeRange;
use App\Domain\Auth\Role;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
use App\Domain\Booking\ResourceType;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Dealership;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use App\Support\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AppointmentRepository implements AppointmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(array $attributes): Appointment
    {
        return Appointment::create($attributes);
    }

    public function find(string $id): ?Appointment
    {
        return Appointment::with([
            'dealership',
            'customer',
            'vehicle',
            'serviceType',
            'technician',
            'bay',
            'createdByUser',
            'cancelledByUser',
        ])->find($id);
    }

    public function findVisibleTo(User $user, string $id): Appointment
    {
        if (! Str::isUuid($id)) {
            throw new AppointmentNotFound;
        }

        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);

        $query = Appointment::with([
            'dealership',
            'customer',
            'vehicle',
            'serviceType',
            'technician',
            'bay',
            'createdByUser',
            'cancelledByUser',
        ])->where('id', $id);

        if ($role === Role::CUSTOMER) {
            $customerId = $user->customer->id ?? $user->customer()->value('id');
            if ($customerId === null) {
                throw new AppointmentNotFound;
            }
            $query->where('customer_id', $customerId);
        } elseif ($role?->isStaff()) {
            if ($user->dealership_id === null) {
                throw new AppointmentNotFound;
            }
            $query->where('dealership_id', $user->dealership_id);
        } else {
            throw new AppointmentNotFound;
        }

        $appointment = $query->first();

        if ($appointment === null) {
            throw new AppointmentNotFound;
        }

        return $appointment;
    }

    /**
     * @param  list<string>|null  $statuses
     * @return CursorPage<Appointment>
     */
    public function listForDealershipDay(
        Dealership $dealership,
        ?string $date = null,
        ?string $technicianId = null,
        ?string $bayId = null,
        ?array $statuses = null,
        int $limit = 50,
        ?string $cursor = null,
        bool $contactRequired = false,
    ): CursorPage {
        $tzName = $dealership->timezone ?? 'UTC';
        $tz = new DateTimeZone($tzName);

        if ($contactRequired) {
            $fromLocal = ($date !== null && $date !== '')
                ? CarbonImmutable::parse($date.' 00:00:00', $tz)
                : CarbonImmutable::now($tz)->startOfDay();
            $fromUtc = $fromLocal->utc();

            $effectiveStatuses = ! empty($statuses)
                ? $statuses
                : [AppointmentStatus::CONFIRMED->value];

            $baseQuery = Appointment::query()
                ->where('dealership_id', $dealership->id)
                ->where('start_at', '>=', $fromUtc)
                ->whereNotNull('contact_required_reason')
                ->whereIn('status', $effectiveStatuses);
        } else {
            $fromLocal = CarbonImmutable::parse((string) $date.' 00:00:00', $tz);
            $toLocal = $fromLocal->addDay();
            $fromUtc = $fromLocal->utc();
            $toUtc = $toLocal->utc();

            $defaultStatuses = [
                AppointmentStatus::HELD->value,
                AppointmentStatus::CONFIRMED->value,
                AppointmentStatus::CHECKED_IN->value,
                AppointmentStatus::IN_PROGRESS->value,
                AppointmentStatus::COMPLETED->value,
                AppointmentStatus::CANCELLED->value,
                AppointmentStatus::NO_SHOW->value,
            ];
            $effectiveStatuses = ! empty($statuses) ? $statuses : $defaultStatuses;

            $baseQuery = Appointment::query()
                ->where('dealership_id', $dealership->id)
                ->where('start_at', '>=', $fromUtc)
                ->where('start_at', '<', $toUtc)
                ->whereIn('status', $effectiveStatuses);
        }

        if ($technicianId !== null && $technicianId !== '') {
            $baseQuery->where('technician_id', $technicianId);
        }

        if ($bayId !== null && $bayId !== '') {
            $baseQuery->where('bay_id', $bayId);
        }

        $total = (clone $baseQuery)->count();

        $dataQuery = (clone $baseQuery);

        if ($cursor !== null && $cursor !== '') {
            $decoded = CursorPaginator::decode($cursor, ['start_at', 'id']);
            $cursorStart = (string) $decoded['start_at'];
            $cursorId = (string) $decoded['id'];

            $dataQuery->where(function ($q) use ($cursorStart, $cursorId) {
                $q->where('start_at', '>', $cursorStart)
                    ->orWhere(function ($sub) use ($cursorStart, $cursorId) {
                        $sub->where('start_at', '=', $cursorStart)
                            ->where('id', '>', $cursorId);
                    });
            });
        }

        $items = $dataQuery
            ->orderBy('start_at', 'asc')
            ->orderBy('id', 'asc')
            ->limit($limit + 1)
            ->with([
                'dealership',
                'customer',
                'vehicle',
                'serviceType',
                'technician',
                'bay',
            ])
            ->get();

        if ($items->count() > $limit) {
            $pageItems = $items->slice(0, $limit);
            $lastItem = $pageItems->last();
            $nextCursor = $lastItem !== null ? CursorPaginator::encode([
                'start_at' => $lastItem->start_at,
                'id' => $lastItem->id,
            ]) : null;
        } else {
            $pageItems = $items;
            $nextCursor = null;
        }

        return new CursorPage(
            items: $pageItems->values(),
            nextCursor: $nextCursor,
            limit: $limit,
            total: $total,
        );
    }

    /**
     * @return CursorPage<Appointment>
     */
    public function listForCustomer(
        User $user,
        string $scope,
        int $limit = 50,
        ?string $cursor = null,
    ): CursorPage {
        $customerId = $user->customer->id ?? $user->customer()->value('id');
        if ($customerId === null) {
            return new CursorPage(
                items: collect(),
                nextCursor: null,
                limit: $limit,
                total: 0,
            );
        }

        $now = CarbonImmutable::now()->utc();

        $baseQuery = Appointment::query()->where('customer_id', $customerId);

        if ($scope === 'upcoming') {
            $baseQuery->whereIn('status', [
                AppointmentStatus::CONFIRMED->value,
                AppointmentStatus::CHECKED_IN->value,
                AppointmentStatus::IN_PROGRESS->value,
            ])->where('end_at', '>', $now);
        } else {
            $baseQuery->where(function ($q) use ($now) {
                $q->whereIn('status', [
                    AppointmentStatus::COMPLETED->value,
                    AppointmentStatus::CANCELLED->value,
                    AppointmentStatus::NO_SHOW->value,
                ])->orWhere(function ($sub) use ($now) {
                    $sub->whereIn('status', [
                        AppointmentStatus::CONFIRMED->value,
                        AppointmentStatus::CHECKED_IN->value,
                        AppointmentStatus::IN_PROGRESS->value,
                    ])->where('end_at', '<=', $now);
                });
            });
        }

        $total = (clone $baseQuery)->count();

        $dataQuery = (clone $baseQuery);

        if ($cursor !== null && $cursor !== '') {
            $decoded = CursorPaginator::decode($cursor, ['start_at', 'id']);
            $cursorStart = (string) $decoded['start_at'];
            $cursorId = (string) $decoded['id'];

            if ($scope === 'upcoming') {
                $dataQuery->where(function ($q) use ($cursorStart, $cursorId) {
                    $q->where('start_at', '>', $cursorStart)
                        ->orWhere(function ($sub) use ($cursorStart, $cursorId) {
                            $sub->where('start_at', '=', $cursorStart)
                                ->where('id', '>', $cursorId);
                        });
                });
            } else {
                $dataQuery->where(function ($q) use ($cursorStart, $cursorId) {
                    $q->where('start_at', '<', $cursorStart)
                        ->orWhere(function ($sub) use ($cursorStart, $cursorId) {
                            $sub->where('start_at', '=', $cursorStart)
                                ->where('id', '<', $cursorId);
                        });
                });
            }
        }

        if ($scope === 'upcoming') {
            $dataQuery->orderBy('start_at', 'asc')->orderBy('id', 'asc');
        } else {
            $dataQuery->orderBy('start_at', 'desc')->orderBy('id', 'desc');
        }

        $items = $dataQuery
            ->limit($limit + 1)
            ->with([
                'dealership',
                'customer',
                'vehicle',
                'serviceType',
                'technician',
                'bay',
            ])
            ->get();

        if ($items->count() > $limit) {
            $pageItems = $items->slice(0, $limit);
            $lastItem = $pageItems->last();
            $nextCursor = $lastItem !== null ? CursorPaginator::encode([
                'start_at' => $lastItem->start_at,
                'id' => $lastItem->id,
            ]) : null;
        } else {
            $pageItems = $items;
            $nextCursor = null;
        }

        return new CursorPage(
            items: $pageItems->values(),
            nextCursor: $nextCursor,
            limit: $limit,
            total: $total,
        );
    }

    /**
     * @return Collection<int, AppointmentEvent>
     */
    public function history(string $appointmentId): Collection
    {
        return AppointmentEvent::with(['actorUser', 'dealership'])
            ->where('appointment_id', $appointmentId)
            ->orderBy('occurred_at', 'asc')
            ->orderBy('version', 'asc')
            ->orderBy('id', 'asc')
            ->limit(200)
            ->get();
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateVersioned(string $id, int $expectedVersion, array $changes, CarbonImmutable $now): int
    {
        $affected = DB::table('appointments')
            ->where('id', $id)
            ->where('version', $expectedVersion)
            ->update(array_merge($changes, [
                'version' => DB::raw('version + 1'),
                'updated_at' => $now,
            ]));

        if ($affected === 0) {
            AppointmentModifiedDetected::dispatch($id, $expectedVersion);
            throw new AppointmentModified($id);
        }

        return $expectedVersion + 1;
    }

    public function hasActiveAllocations(string $appointmentId): bool
    {
        return DB::table('resource_allocations')
            ->where('appointment_id', $appointmentId)
            ->where('active', true)
            ->exists();
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function findExpiredHoldsBlocking(
        string $dealershipId,
        TimeRange $range,
        ?string $vehicleId,
        CarbonImmutable $now,
    ): Collection {
        $query = Appointment::query()
            ->join('resource_allocations as ra', 'ra.appointment_id', '=', 'appointments.id')
            ->where('ra.active', true)
            ->whereRaw("ra.period && tstzrange(?, ?, '[)')", [
                $range->start->toIso8601String(),
                $range->end->toIso8601String(),
            ])
            ->where('appointments.status', AppointmentStatus::HELD->value)
            ->where('appointments.expires_at', '<=', $now)
            ->where(function ($q) use ($dealershipId, $vehicleId) {
                $q->where('ra.dealership_id', $dealershipId);
                if ($vehicleId !== null && $vehicleId !== '') {
                    $q->orWhere(function ($sub) use ($vehicleId) {
                        $sub->where('ra.resource_type', ResourceType::VEHICLE->value)
                            ->where('ra.resource_id', $vehicleId);
                    });
                }
            })
            ->select('appointments.*')
            ->distinct()
            ->orderBy('appointments.expires_at', 'asc');

        /** @var Collection<int, Appointment> $results */
        $results = $query->get();

        return $results;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function findExpiredHolds(
        CarbonImmutable $now,
        int $limit = 500,
    ): Collection {
        /** @var Collection<int, Appointment> $results */
        $results = Appointment::query()
            ->where('status', AppointmentStatus::HELD->value)
            ->where('expires_at', '<=', $now)
            ->orderBy('expires_at', 'asc')
            ->limit($limit)
            ->get();

        return $results;
    }

    /**
     * @return Collection<int, Appointment>
     */
    public function findReminderCandidates(
        CarbonImmutable $from,
        CarbonImmutable $to,
    ): Collection {
        /** @var Collection<int, Appointment> $results */
        $results = Appointment::query()
            ->where('status', AppointmentStatus::CONFIRMED->value)
            ->where('reminder_opt_in', true)
            ->where('start_at', '>=', $from->utc())
            ->where('start_at', '<', $to->utc())
            ->orderBy('start_at', 'asc')
            ->get();

        return $results;
    }

    /**
     * @return list<Appointment>
     */
    public function findActiveNeighbours(
        string $appointmentId,
        string $technicianId,
        string $bayId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
    ): array {
        /** @var list<string> $ids */
        $ids = DB::table('appointments as a')
            ->join('resource_allocations as ra', 'ra.appointment_id', '=', 'a.id')
            ->where('ra.active', true)
            ->where(function ($query) use ($technicianId, $bayId): void {
                $query->where(function ($q) use ($technicianId): void {
                    $q->where('ra.resource_type', ResourceType::TECHNICIAN->value)
                        ->where('ra.resource_id', $technicianId);
                })->orWhere(function ($q) use ($bayId): void {
                    $q->where('ra.resource_type', ResourceType::BAY->value)
                        ->where('ra.resource_id', $bayId);
                });
            })
            ->whereRaw("ra.period && tstzrange(?, ?, '[)')", [
                $startAt->utc()->toIso8601String(),
                $endAt->utc()->toIso8601String(),
            ])
            ->where('a.id', '<>', $appointmentId)
            ->select('a.id')
            ->distinct()
            ->orderBy('a.id', 'asc')
            ->pluck('id')
            ->all();

        if (empty($ids)) {
            return [];
        }

        return array_values(Appointment::query()
            ->whereIn('id', $ids)
            ->orderBy('id', 'asc')
            ->get()
            ->all());
    }
}
