<?php

namespace App\Domain\Workshop\Services;

use App\Models\Appointment;

readonly class ExtendResult
{
    /**
     * @param  list<array<string, mixed>>  $affected
     */
    public function __construct(
        public Appointment $appointment,
        public array $affected = [],
    ) {}
}
