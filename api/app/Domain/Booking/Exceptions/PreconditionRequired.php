<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class PreconditionRequired extends ProblemException
{
    public function __construct(string $title = 'If-Match header is required.')
    {
        parent::__construct(
            status: 428,
            problemCode: 'PRECONDITION_REQUIRED',
            title: $title,
        );
    }
}
