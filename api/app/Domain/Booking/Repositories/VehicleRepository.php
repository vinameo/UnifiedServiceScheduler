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

    public function countByCustomer(string $customerId): int
    {
        return Vehicle::query()->where('customer_id', $customerId)->count();
    }

    public function findByPlateKey(string $plateKey): ?Vehicle
    {
        return Vehicle::query()->where('plate_key', $plateKey)->first();
    }

    public function findByVin(string $vin): ?Vehicle
    {
        return Vehicle::query()->where('vin', $vin)->first();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Vehicle
    {
        return Vehicle::create($attributes);
    }
}
