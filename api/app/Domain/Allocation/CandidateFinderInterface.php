<?php

namespace App\Domain\Allocation;

interface CandidateFinderInterface
{
    public function find(AllocationRequest $request, ResourceSnapshot $snapshot): AllocationResult;
}
