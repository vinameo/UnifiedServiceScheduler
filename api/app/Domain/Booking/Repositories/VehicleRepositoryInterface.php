<?php

namespace App\Domain\Booking\Repositories;

use App\Models\Vehicle;

interface VehicleRepositoryInterface
{
    public function find(string $id): ?Vehicle;
}

