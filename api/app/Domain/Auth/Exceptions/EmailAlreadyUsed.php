<?php

namespace App\Domain\Auth\Exceptions;

use App\Support\Problem\ProblemException;

class EmailAlreadyUsed extends ProblemException
{
    public function __construct(string $detail = 'An account with this email already exists. Sign in instead.')
    {
        parent::__construct(
            status: 409,
            problemCode: 'EMAIL_ALREADY_USED',
            title: 'An account with this email already exists. Sign in instead.',
            detail: $detail,
        );
    }
}
