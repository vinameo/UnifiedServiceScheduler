<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class HoldNotAllowed extends ProblemException
{
    public function __construct()
    {
        parent::__construct(
            status: 422,
            problemCode: 'HOLD_NOT_ALLOWED',
            title: 'Temporary hold is only available for customers.',
            detail: 'Staff cannot hold slots.',
        );
    }
}
