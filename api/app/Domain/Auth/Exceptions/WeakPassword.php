<?php

namespace App\Domain\Auth\Exceptions;

use App\Support\Problem\ProblemException;

class WeakPassword extends ProblemException
{
    public function __construct(string $detail = 'Password must be at least 8 characters, with a letter and a number.')
    {
        parent::__construct(
            status: 422,
            problemCode: 'WEAK_PASSWORD',
            title: 'Password must be at least 8 characters, with a letter and a number.',
            detail: $detail,
        );
    }
}
