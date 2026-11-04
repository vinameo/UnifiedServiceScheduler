<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;

class TooLateToCancel extends ProblemException
{
    public function __construct(
        public readonly string $dealershipPhone,
        public readonly string $start,
    ) {
        parent::__construct(
            status: 422,
            problemCode: 'TOO_LATE_TO_CANCEL',
            title: "It's less than 2 hours before your appointment.",
            extra: [
                'dealershipPhone' => $dealershipPhone,
                'start' => $start,
            ],
        );
    }
}
