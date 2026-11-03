<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class VehicleAlreadyBooked extends ProblemException
{
    /**
     * @param  array{id: string}|null  $existingAppointment
     */
    public function __construct(?array $existingAppointment = null)
    {
        parent::__construct(
            status: 409,
            problemCode: 'VEHICLE_ALREADY_BOOKED',
            title: 'This vehicle already has an appointment at this time.',
            extra: $existingAppointment !== null ? ['existingAppointment' => $existingAppointment] : [],
        );
    }
}
