<?php

namespace App\Domain\Allocation;

use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use InvalidArgumentException;

final readonly class AllocationRequest
{
    public function __construct(
        public string $dealershipId,
        public Level $requiredLevel,
        public BayType $requiredBayType,
        public TimeRange $range,
        public ?string $preferTechnicianId = null,
        public ?string $preferBayId = null,
        public ?string $excludeAppointmentId = null,
        public int $maxPairs = 5,
    ) {
        if ($this->maxPairs < 1) {
            throw new InvalidArgumentException("maxPairs must be at least 1, got {$this->maxPairs}.");
        }
    }
}
