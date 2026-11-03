<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class StartInPast extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 422,
            problemCode: 'START_IN_PAST',
            title: 'This time has already passed. Pick another time.',
        );
    }
}

