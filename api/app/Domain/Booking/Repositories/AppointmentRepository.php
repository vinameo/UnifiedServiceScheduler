<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Auth\Role;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Exceptions\AppointmentNotFound;
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
        string $date,
        ?string $technicianId = null,
        ?string $bayId = null,
        ?array $statuses = null,
        int $limit = 50,
        ?string $cursor = null,
    ): CursorPage {
        $tzName = $dealership->timezone ?? 'UTC';
        $tz = new DateTimeZone($tzName);

        $fromLocal = CarbonImmutable::parse($date.' 00:00:00', $tz);
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
}
