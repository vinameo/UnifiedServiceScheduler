<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\CreateBookingInput;
use App\Models\Appointment;
use App\Models\User;

interface BookingServiceInterface
{
    public function create(CreateBookingInput $input, User $actor): Appointment;
}

