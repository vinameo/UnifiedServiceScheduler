<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class CustomerNotFound extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 404,
            problemCode: 'CUSTOMER_NOT_FOUND',
            title: 'Customer not found.',
        );
    }
}
