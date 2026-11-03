<?php

namespace App\Domain\Allocation;

final readonly class BusyInterval
{
    public function __construct(
        public TimeRange $range,
        public ?string $appointmentId = null,
        public ?string $resourceType = null,
        public ?string $resourceId = null,
    ) {}
}

