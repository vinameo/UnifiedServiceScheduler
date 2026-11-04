<?php

namespace App\Domain\Workshop\Services;

use App\Domain\Allocation\ResourceSnapshot;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

interface ReassignerInterface
{
    /**
     * One extend neighbour, inside the extend transaction. INV-12.4, INV-12.7.
     */
    public function reassign(
        Appointment $neighbour,
        ResourceSnapshot $snapshotWithExtension,
        CarbonImmutable $now,
        ?User $actor = null,
    ): ReassignmentResult;
}
