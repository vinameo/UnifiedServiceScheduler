<?php

namespace App\Domain\Booking;

use Carbon\CarbonImmutable;

final readonly class SlotState
{
    public function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
        public bool $available,
        public ?string $reason = null,
    ) {}
}
