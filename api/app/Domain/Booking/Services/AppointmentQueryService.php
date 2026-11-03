<?php

namespace App\Domain\Booking\Services;

use App\Models\Appointment;

class AppointmentQueryService implements AppointmentQueryServiceInterface
{
    public function show(Appointment $appointment): Appointment
    {
        return $appointment;
    }
}
