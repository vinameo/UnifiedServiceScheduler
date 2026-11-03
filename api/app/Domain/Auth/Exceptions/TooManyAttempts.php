<?php

namespace App\Domain\Auth\Exceptions;

use App\Support\Problem\ProblemException;

class TooManyAttempts extends ProblemException
{
    public function __construct(int $retryAfterSeconds)
    {
        parent::__construct(
            status: 429,
            problemCode: 'TOO_MANY_ATTEMPTS',
            title: 'Too many attempts. Try again later.',
            detail: 'Too many attempts. Try again later.',
            extra: [
                'retryAfterSeconds' => $retryAfterSeconds,
            ],
            headers: [
                'Retry-After' => (string) $retryAfterSeconds,
            ],
        );
    }
}
