<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
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
     * @return Collection<int, AppointmentEvent>
     */
    public function history(Appointment $appointment): Collection
    {
        return $this->appointmentRepository->history($appointment->id);
    }
}
