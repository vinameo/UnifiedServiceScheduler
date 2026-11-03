<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class VehicleLimitReached extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 422,
            problemCode: 'VEHICLE_LIMIT_REACHED',
            title: 'Customer has reached the maximum of 10 vehicles.',
        );
    }
}
