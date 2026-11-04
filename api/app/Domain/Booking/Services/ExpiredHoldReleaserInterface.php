<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;

interface ExpiredHoldReleaserInterface
{
    /**
     * INV-10.6. Returns the number of holds expired. Each in its own transaction, before the caller's write.
     */
    public function releaseBlocking(
        string $dealershipId,
        TimeRange $range,
        ?string $vehicleId,
        CarbonImmutable $now,
    ): int;
}
