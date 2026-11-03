<?php

namespace App\Domain\Allocation;

final readonly class CandidatePair
{
    public function __construct(
        public TechnicianState $technician,
        public BayState $bay,
    ) {}
}

