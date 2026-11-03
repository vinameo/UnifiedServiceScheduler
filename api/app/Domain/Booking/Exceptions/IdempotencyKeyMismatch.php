<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class IdempotencyKeyMismatch extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 422,
            problemCode: 'IDEMPOTENCY_KEY_MISMATCH',
            title: 'Idempotency key mismatch.',
            detail: 'The provided Idempotency-Key was previously used with a different request payload or endpoint.',
        );
    }
}
