<?php

namespace App\Domain\Allocation;

use App\Domain\MasterData\BayType;

final readonly class BayState
{
    /**
     * @param  list<BusyInterval>  $busy
     */
    public function __construct(
        public string $id,
        public string $code,
        public BayType $bayType,
        public bool $active = true,
        public array $busy = [],
    ) {}
}
