<?php

namespace App\Domain\Allocation;

interface AllocationStrategy
{
    /**
     * @param  list<TechnicianState>  $free
     * @return list<TechnicianState>
     */
    public function rankTechnicians(array $free, AllocationRequest $request, ResourceSnapshot $snapshot): array;

    /**
     * @param  list<BayState>  $free
     * @return list<BayState>
     */
    public function rankBays(array $free, AllocationRequest $request): array;
}
