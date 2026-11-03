<?php

namespace App\Domain\Allocation;

final readonly class AllocationResult
{
    /**
     * @param  list<CandidatePair>  $pairs
     */
    public function __construct(
        public array $pairs,
        public ?AllocationFailure $failure = null,
    ) {}

    public function found(): bool
    {
        return count($this->pairs) > 0;
    }
}
