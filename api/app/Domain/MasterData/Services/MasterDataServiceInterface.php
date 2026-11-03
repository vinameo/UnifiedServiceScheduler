<?php

namespace App\Domain\MasterData\Services;

use App\Models\Bay;
use App\Models\Dealership;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;

interface MasterDataServiceInterface
{
    /**
     * @return list<ServiceType>
     */
    public function serviceTypes(User $user, Dealership $dealership, bool $includeInactive = true): array;

    /**
     * @return list<Dealership>
     */
    public function listDealerships(User $user): array;

    /**
     * @return list<Bay>
     */
    public function bays(User $user, Dealership $dealership): array;

    /**
     * @return list<Technician>
     */
    public function technicians(User $user, Dealership $dealership): array;
}
