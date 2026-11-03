<?php

namespace App\Domain\Booking\Events;

use App\Domain\Booking\ResourceType;
use Illuminate\Foundation\Events\Dispatchable;

class AllocationRetried
{
    use Dispatchable;

    public function __construct(
        public readonly string $dealershipId,
        public readonly ResourceType $resourceType,
        public readonly string $resourceId,
    ) {}
}
