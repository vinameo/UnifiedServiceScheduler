<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;
use App\Models\Vehicle;

interface CustomerServiceInterface
{
    public function search(string $query, int $limit = 20): CustomerSearchResult;

    /**
     * @return list<Vehicle>
     */
    public function vehicles(string $customerId): array;
}
