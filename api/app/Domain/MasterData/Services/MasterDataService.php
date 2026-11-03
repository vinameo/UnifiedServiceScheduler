<?php

namespace App\Domain\MasterData\Services;

use App\Domain\Auth\Role;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\Dealership;
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

        $scopedId = $isStaff ? $user->dealership_id : null;

        return $this->masterDataRepository->dealerships($scopedId);
    }
}
