<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\Contracts\BusyIntervalReader;
use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;

final class NoBusyIntervals implements BusyIntervalReader
{
    /**
     * @return list<BusyInterval>
     */
    public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
    {
        return [];
    }
}
