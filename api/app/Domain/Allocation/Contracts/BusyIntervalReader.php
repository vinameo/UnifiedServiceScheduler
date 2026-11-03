<?php

namespace App\Domain\Allocation\Contracts;

use App\Domain\Allocation\BusyInterval;
use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;

interface BusyIntervalReader
{
    /**
     * @return list<BusyInterval>
     */
    public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array;
}
