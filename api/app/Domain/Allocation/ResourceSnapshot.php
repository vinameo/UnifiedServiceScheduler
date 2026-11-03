<?php

namespace App\Domain\Allocation;

final readonly class ResourceSnapshot
{
    /**
     * @param  list<TechnicianState>  $technicians
     * @param  list<BayState>  $bays
     */
    public function __construct(
        public string $dealershipId,
        public string $timezone,
        public string $localDate,
        public array $technicians = [],
        public array $bays = [],
    ) {}
}
