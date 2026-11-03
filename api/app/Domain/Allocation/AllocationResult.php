<?php

namespace App\Domain\Allocation;

use InvalidArgumentException;

final readonly class AllocationResult
{
    /**
     * @param  list<CandidatePair>  $pairs
     */
    public function __construct(
        public array $pairs,
        public ?AllocationFailure $failure = null,
    ) {
        if (count($this->pairs) > 0 && $this->failure !== null) {
            throw new InvalidArgumentException('AllocationResult cannot have both pairs and a failure reason (INV-03.7).');
        }

        if (count($this->pairs) === 0 && $this->failure === null) {
            throw new InvalidArgumentException('AllocationResult must have a failure reason when pairs is empty (INV-03.7).');
        }
    }

    public function found(): bool
    {
        return count($this->pairs) > 0;
    }
}
