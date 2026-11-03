<?php

namespace App\Domain\MasterData\Services;

use App\Domain\Auth\Role;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\Bay;
use App\Models\Dealership;
use App\Models\Technician;
use App\Models\User;

class MasterDataService implements MasterDataServiceInterface
{
    public function __construct(
        private readonly MasterDataRepositoryInterface $masterDataRepository,
    ) {}

    public function serviceTypes(User $user, Dealership $dealership, bool $includeInactive = true): array
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);
        $isStaff = $role?->isStaff() ?? false;

        // INV-01.4 & TC-01-13: Customers only see active services; includeInactive param is ignored for customers.
        $activeOnly = ! $isStaff || ! $includeInactive;

        return $this->masterDataRepository->serviceTypes($dealership->id, $activeOnly);
    }

    public function listDealerships(User $user): array
    {
        $role = $user->role instanceof Role ? $user->role : Role::tryFrom((string) $user->role);
        $isStaff = $role?->isStaff() ?? false;

        if ($isStaff) {
            if ($user->dealership_id === null) {
                return [];
            }

            return $this->masterDataRepository->dealerships($user->dealership_id);
        }

        return $this->masterDataRepository->dealerships();
    }

    /**
     * @return list<Bay>
     */
    public function bays(User $user, Dealership $dealership): array
    {
        $bays = $this->masterDataRepository->bays($dealership->id);
        $activeServices = $this->masterDataRepository->serviceTypes($dealership->id, activeOnly: true);

        foreach ($bays as $bay) {
            $supportedIds = [];
            foreach ($activeServices as $service) {
                if ($service->required_bay_type === $bay->bay_type) {
                    $supportedIds[] = (string) $service->id;
                }
            }
            $bay->supportedServiceTypeIds = $supportedIds;
        }

        return $bays;
    }

    /**
     * @return list<Technician>
     */
    public function technicians(User $user, Dealership $dealership): array
    {
        return $this->masterDataRepository->technicians($dealership->id);
    }
}
