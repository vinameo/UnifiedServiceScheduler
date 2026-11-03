<?php

namespace App\Domain\MasterData\Repositories;

use App\Models\Bay;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;

class MasterDataRepository implements MasterDataRepositoryInterface
{
    /**
     * @return list<Dealership>
     */
    public function dealerships(?string $scopedDealershipId = null): array
    {
        $query = Dealership::query()
            ->with(['openingHours' => fn ($q) => $q->orderBy('weekday')])
            ->orderBy('name');

        if ($scopedDealershipId !== null) {
            $query->where('id', $scopedDealershipId);
        }

        return array_values($query->get()->all());
    }

    /**
     * @return list<ServiceType>
     */
    public function serviceTypes(string $dealershipId, bool $activeOnly = false): array
    {
        $query = ServiceType::query()
            ->where('dealership_id', $dealershipId)
            ->orderBy('name');

        if ($activeOnly) {
            $query->where('active', true);
        }

        return array_values($query->get()->all());
    }

    public function serviceTypeFor(string $dealershipId, string $serviceTypeId): ?ServiceType
    {
        return ServiceType::query()
            ->where('dealership_id', $dealershipId)
            ->where('id', $serviceTypeId)
            ->first();
    }

    /**
     * @return list<Technician>
     */
    public function technicians(string $dealershipId): array
    {
        return array_values(Technician::query()
            ->where('dealership_id', $dealershipId)
            ->orderBy('name')
            ->get()
            ->all());
    }

    /**
     * @return list<Bay>
     */
    public function bays(string $dealershipId): array
    {
        return array_values(Bay::query()
            ->where('dealership_id', $dealershipId)
            ->orderBy('code')
            ->get()
            ->all());
    }

    /**
     * @return list<DealershipOpeningHour>
     */
    public function openingHours(string $dealershipId): array
    {
        return array_values(DealershipOpeningHour::query()
            ->where('dealership_id', $dealershipId)
            ->orderBy('weekday')
            ->get()
            ->all());
    }
}
