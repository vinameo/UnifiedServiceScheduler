<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;
use Carbon\CarbonImmutable;

class NoAvailability extends ProblemException
{
    public function __construct(
        string $reason,
        CarbonImmutable $requestedStart,
        CarbonImmutable $requestedEnd,
        ?string $dealershipId = null,
        ?string $detail = null,
    ) {
        parent::__construct(
            status: 409,
            problemCode: 'NO_AVAILABILITY',
            title: 'No free technician and bay for this time.',
            detail: $detail,
            extra: array_filter([
                'reason' => $reason,
                'requestedStart' => $requestedStart->toIso8601String(),
                'requestedEnd' => $requestedEnd->toIso8601String(),
                'dealershipId' => $dealershipId,
            ]),
        );
    }
}
