<?php

namespace App\Domain\Booking\Repositories;

use App\Models\Vehicle;

final class VehicleRepository implements VehicleRepositoryInterface
{
    public function find(string $id): ?Vehicle
    {
        return Vehicle::query()->where('id', $id)->first();
    }
}
