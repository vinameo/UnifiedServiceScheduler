<?php

namespace App\Domain\MasterData\Services;

use App\Domain\MasterData\Exceptions\TechnicianHasBookings;
use App\Domain\MasterData\Exceptions\TechnicianNotFound;
use App\Models\Dealership;
use App\Models\Technician;
use Carbon\CarbonImmutable;

interface TechnicianServiceInterface
{
    /**
     * Set the active status of a technician.
     * Disabling requires that the technician has no upcoming appointments.
     * Setting the current active value again is an idempotent no-op.
     *
     * @throws TechnicianNotFound
     * @throws TechnicianHasBookings
     */
    public function setActive(
        Dealership $dealership,
        Technician $technician,
        bool $active,
        ?CarbonImmutable $now = null,
    ): Technician;
}
