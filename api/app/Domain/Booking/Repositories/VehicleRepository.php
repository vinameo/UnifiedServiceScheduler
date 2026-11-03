<?php

namespace App\Domain\Booking\Repositories;

use App\Models\Vehicle;

final class VehicleRepository implements VehicleRepositoryInterface
{
    public function find(string $id): ?Vehicle
    {
        return Vehicle::query()->where('id', $id)->first();
    }

    /**
     * @return list<Vehicle>
     */
    public function findByCustomer(string $customerId): array
    {
        return array_values(
            Vehicle::query()
                ->where('customer_id', $customerId)
                ->orderBy('created_at')
                ->get()
                ->all()
        );
    }
}
