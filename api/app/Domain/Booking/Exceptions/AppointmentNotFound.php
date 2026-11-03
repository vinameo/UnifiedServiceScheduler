<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class AppointmentNotFound extends ProblemException
{
    public function __construct(?string $id = null)
    {
        parent::__construct(
            status: 404,
            problemCode: 'APPOINTMENT_NOT_FOUND',
            title: "We couldn't find this appointment.",
            detail: $id !== null && $id !== '' ? "Appointment {$id} was not found." : null,
        );
    }
}
