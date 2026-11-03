<?php

namespace App\Domain\Booking\Services;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Dealership;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Illuminate\Support\Collection;

interface AppointmentQueryServiceInterface
{
    public function show(Appointment $appointment): Appointment;

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
    public function history(Appointment $appointment): Collection;
}
