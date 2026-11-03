<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class VehicleNotFound extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 404,
            problemCode: 'VEHICLE_NOT_FOUND',
            title: 'Vehicle not found.',
        );
    }
}

