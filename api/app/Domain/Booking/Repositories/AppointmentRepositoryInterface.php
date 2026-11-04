<?php

namespace App\Domain\Booking\Repositories;

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
        string $date,
        ?string $technicianId = null,
        ?string $bayId = null,
        ?array $statuses = null,
        int $limit = 50,
        ?string $cursor = null,
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
}
