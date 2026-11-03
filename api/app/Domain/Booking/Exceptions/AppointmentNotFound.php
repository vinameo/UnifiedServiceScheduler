<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class AppointmentNotFound extends ProblemException
{
    public function __construct(string $id)
    {
        parent::__construct(
            status: 404,
            problemCode: 'NOT_FOUND',
            title: 'Appointment not found.',
            detail: "Appointment {$id} was not found.",
        );
    }
}
