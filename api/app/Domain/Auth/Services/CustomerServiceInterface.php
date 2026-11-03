<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;
use App\Models\User;
use App\Models\Vehicle;

interface CustomerServiceInterface
{
    public function search(string $query, int $limit = 20): CustomerSearchResult;

    /**
     * @return list<Vehicle>
     */
    public function vehicles(string $customerId): array;

    /**
     * @return list<Vehicle>
     */
    public function myVehicles(User $user): array;

    /**
     * @param  array{licencePlate: string, make: string, model: string, year: ?int, vin: ?string}  $data
     */
    public function addVehicle(User $user, array $data): Vehicle;
}
