<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class VehicleNotOwnedByCustomer extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 422,
            problemCode: 'VEHICLE_NOT_OWNED_BY_CUSTOMER',
            title: 'This vehicle is not registered to the selected customer.',
        );
    }
}

