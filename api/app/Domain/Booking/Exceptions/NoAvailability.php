<?php

namespace App\Domain\Booking\Exceptions;

use App\Support\Problem\ProblemException;
use Carbon\CarbonImmutable;

class NoAvailability extends ProblemException
{
    /**
     * @param  list<array{start: string, end: string}>  $alternatives
     */
    public function __construct(
        public readonly string $reason,
        public readonly CarbonImmutable $requestedStart,
        public readonly CarbonImmutable $requestedEnd,
        public readonly ?string $dealershipId = null,
        ?string $detail = null,
        public readonly array $alternatives = [],
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
                'alternatives' => $alternatives,
            ], fn ($value) => $value !== null),
        );
    }
}
