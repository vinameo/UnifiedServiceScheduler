<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class IdempotencyKeyRequired extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 400,
            problemCode: 'IDEMPOTENCY_KEY_REQUIRED',
            title: 'Idempotency key required.',
            detail: 'Writing POST requests require an Idempotency-Key header.',
        );
    }
}
