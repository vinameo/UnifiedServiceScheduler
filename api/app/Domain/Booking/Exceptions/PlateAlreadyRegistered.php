<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class PlateAlreadyRegistered extends ProblemException
{
    public function __construct(string $plate)
    {
        parent::__construct(
            status: 409,
            problemCode: 'PLATE_ALREADY_REGISTERED',
            title: "A vehicle with licence plate {$plate} is already registered.",
        );
    }
}
