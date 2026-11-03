<?php

namespace App\Domain\Booking\Services;

use App\Models\Appointment;
use App\Models\AppointmentEvent;
use Illuminate\Support\Collection;

interface AppointmentQueryServiceInterface
{
    public function show(Appointment $appointment): Appointment;

    /**
     * @return Collection<int, AppointmentEvent>
     */
    public function history(Appointment $appointment): Collection;
}
