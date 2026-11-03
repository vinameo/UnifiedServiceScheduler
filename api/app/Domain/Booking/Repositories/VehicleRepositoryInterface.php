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
}
