<?php

namespace App\Domain\Booking\Services;

use App\Models\Appointment;

interface AppointmentQueryServiceInterface
{
    public function show(Appointment $appointment): Appointment;
}
