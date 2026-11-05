<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\TimeRange;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Dealership;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface AppointmentRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insert(array $attributes): Appointment;

    public function find(string $id): ?Appointment;

    public function findVisibleTo(User $user, string $id): Appointment;

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
    ): CursorPage;

    /**
     * @return CursorPage<Appointment>
     */
    public function listForCustomer(
        User $user,
        string $scope,
        int $limit = 50,
        ?string $cursor = null,
    ): CursorPage;

    /**
     * @return Collection<int, AppointmentEvent>
     */
    public function history(string $appointmentId): Collection;

    /**
     * @param  array<string, mixed>  $changes
     */
    public function updateVersioned(string $id, int $expectedVersion, array $changes, CarbonImmutable $now): int;

    public function hasActiveAllocations(string $appointmentId): bool;

    /**
     * @return Collection<int, Appointment>
     */
    public function findExpiredHoldsBlocking(
        string $dealershipId,
        TimeRange $range,
        ?string $vehicleId,
        CarbonImmutable $now,
    ): Collection;

    /**
     * @return Collection<int, Appointment>
     */
    public function findExpiredHolds(
        CarbonImmutable $now,
        int $limit = 500,
    ): Collection;

    /**
     * Find confirmed appointments with reminder opt-in whose start_at is within [from, to) (D-11.6).
     *
     * @return Collection<int, Appointment>
     */
    public function findReminderCandidates(CarbonImmutable $from, CarbonImmutable $to): Collection;

    /**
     * The workshop board's appointments of one dealership day (D-12.13), oldest first:
     * HELD holds still running, CONFIRMED, CHECKED_IN, IN_PROGRESS, COMPLETED, and CANCELLED
     * (kept only to say that the next booking was cancelled). EXPIRED, NO_SHOW and holds
     * past `expires_at` are left out.
     *
     * @return list<Appointment>
     */
    public function listForBoard(
        string $dealershipId,
        CarbonImmutable $fromUtc,
        CarbonImmutable $toUtc,
        CarbonImmutable $now,
    ): array;

    /**
     * Find active bookings on the given technician or bay whose period overlaps [startAt, endAt),
     * excluding the given appointment. Ordered by id ASC (lock order).
     *
     * @return list<Appointment>
     */
    public function findActiveNeighbours(
        string $appointmentId,
        string $technicianId,
        string $bayId,
        CarbonImmutable $startAt,
        CarbonImmutable $endAt,
    ): array;
}
