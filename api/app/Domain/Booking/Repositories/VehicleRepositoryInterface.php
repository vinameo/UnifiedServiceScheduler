<?php

namespace App\Domain\Booking\Repositories;

use App\Models\Vehicle;

interface VehicleRepositoryInterface
{
    public function find(string $id): ?Vehicle;

    /**
     * @return list<Vehicle>
     */
    public function findByCustomer(string $customerId): array;

    public function countByCustomer(string $customerId): int;

    public function findByPlateKey(string $plateKey): ?Vehicle;

    public function findByVin(string $vin): ?Vehicle;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Vehicle;
}
