<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class StartTooFarAhead extends ProblemException
{
    public function __construct(string $maxDate)
    {
        parent::__construct(
            status: 422,
            problemCode: 'START_TOO_FAR_AHEAD',
            title: 'You can book up to 90 days ahead.',
            extra: [
                'maxDate' => $maxDate,
            ],
        );
    }
}

