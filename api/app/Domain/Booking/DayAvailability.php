<?php

namespace App\Domain\Booking;

final readonly class DayAvailability
{
    /**
     * @param  list<SlotState>  $slots
     */
    public function __construct(
        public string $date,
        public string $timezone,
        public bool $closed,
        public ?string $opensAt,
        public ?string $closesAt,
        public int $stepMinutes,
        public int $durationMinutes,
        public int $freeCount,
        public array $slots,
    ) {}
}
