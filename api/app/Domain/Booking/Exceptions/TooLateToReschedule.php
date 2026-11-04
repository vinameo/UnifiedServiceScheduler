<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class TooLateToReschedule extends ProblemException
{
    public function __construct(
        public readonly string $dealershipPhone,
        public readonly ?string $start = null,
    ) {
        $extra = ['dealershipPhone' => $dealershipPhone];
        if ($start !== null) {
            $extra['start'] = $start;
        }

        parent::__construct(
            status: 422,
            problemCode: 'TOO_LATE_TO_RESCHEDULE',
            title: "It's less than 2 hours before your appointment.",
            detail: "It's less than 2 hours before your appointment. Please call {$dealershipPhone} to change it.",
            extra: $extra,
        );
    }
}
