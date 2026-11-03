<?php

namespace App\Domain\MasterData\Repositories;

use App\Models\Bay;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;

interface MasterDataRepositoryInterface
{
    /**
     * @return list<ServiceType>
     */
    public function serviceTypes(string $dealershipId, bool $activeOnly = false): array;

    public function serviceTypeFor(string $dealershipId, string $serviceTypeId): ?ServiceType;

    /**
     * @return list<Technician>
     */
    public function technicians(string $dealershipId): array;

    /**
     * @return list<Bay>
     */
    public function bays(string $dealershipId): array;

    /**
     * @return list<DealershipOpeningHour>
     */
    public function openingHours(string $dealershipId): array;
}
