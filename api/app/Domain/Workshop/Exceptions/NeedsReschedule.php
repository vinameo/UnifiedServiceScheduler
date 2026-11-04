<?php

namespace App\Domain\Workshop\Exceptions;

use App\Support\Problem\ProblemException;

class NeedsReschedule extends ProblemException
{
    public function __construct(string $detail = 'This booking no longer has a bay or technician. Reschedule it before checking in.')
    {
        parent::__construct(
            status: 422,
            problemCode: 'NEEDS_RESCHEDULE',
            title: 'Appointment needs to be rescheduled before check-in.',
            detail: $detail,
        );
    }
}
