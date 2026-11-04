<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class RescheduleSameTime extends ProblemException
{
    public function __construct(string $detail = 'The new appointment time must differ from the current time.')
    {
        parent::__construct(
            status: 422,
            problemCode: 'RESCHEDULE_SAME_TIME',
            title: 'New time is the same as current time.',
            detail: $detail,
        );
    }
}
