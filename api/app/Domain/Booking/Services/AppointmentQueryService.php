<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Dealership;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Illuminate\Support\Collection;

class AppointmentQueryService implements AppointmentQueryServiceInterface
{
    public function __construct(
        private AppointmentRepositoryInterface $appointmentRepository,
    ) {}

    public function show(Appointment $appointment): Appointment
    {
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
        return $this->appointmentRepository->listForDealershipDay(
            dealership: $dealership,
            date: $date,
            technicianId: $technicianId,
            bayId: $bayId,
            statuses: $statuses,
            limit: $limit,
            cursor: $cursor,
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
        return $this->appointmentRepository->listForCustomer(
            user: $user,
            scope: $scope,
            limit: $limit,
            cursor: $cursor,
        );
    }

    /**
     * @return Collection<int, AppointmentEvent>
     */
    public function history(Appointment $appointment): Collection
    {
        return $this->appointmentRepository->history($appointment->id);
    }
}
