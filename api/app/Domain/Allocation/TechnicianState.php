<?php

namespace App\Domain\Allocation;

use App\Domain\MasterData\Level;

final readonly class TechnicianState
{
    /**
     * @param  list<BusyInterval>  $busy
     */
    public function __construct(
        public string $id,
        public string $name,
        public Level $level,
        public bool $active = true,
        public array $busy = [],
    ) {}
}
