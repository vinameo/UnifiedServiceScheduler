<?php

namespace App\Domain\Auth\Exceptions;

use App\Support\Problem\ProblemException;

class InvalidCredentials extends ProblemException
{
    public function __construct(string $detail = 'Email or password is incorrect.')
    {
        parent::__construct(
            status: 401,
            problemCode: 'INVALID_CREDENTIALS',
            title: 'Email or password is incorrect.',
            detail: $detail,
        );
    }
}
