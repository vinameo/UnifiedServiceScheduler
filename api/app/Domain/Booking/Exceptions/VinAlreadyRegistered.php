<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class VinAlreadyRegistered extends ProblemException
{
    public function __construct(string $vin)
    {
        parent::__construct(
            status: 409,
            problemCode: 'VIN_ALREADY_REGISTERED',
            title: "A vehicle with VIN {$vin} is already registered.",
        );
    }
}
