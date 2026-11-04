<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class HoldExpired extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 410,
            problemCode: 'HOLD_EXPIRED',
            title: 'Your hold has expired.',
            detail: 'The slot is no longer held.',
        );
    }
}
