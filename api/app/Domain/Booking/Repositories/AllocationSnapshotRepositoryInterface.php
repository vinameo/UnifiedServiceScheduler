<?php

namespace App\Domain\Booking\Repositories;

use App\Domain\Allocation\ResourceSnapshot;
use Carbon\CarbonImmutable;

interface AllocationSnapshotRepositoryInterface
{
    public function forDay(string $dealershipId, string $localDate, CarbonImmutable $now): ResourceSnapshot;
}
