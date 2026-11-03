<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class RequestInProgress extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 409,
            problemCode: 'REQUEST_IN_PROGRESS',
            title: 'Request in progress.',
            detail: 'A request with this Idempotency-Key is currently being processed. Please retry shortly.',
        );
    }
}
