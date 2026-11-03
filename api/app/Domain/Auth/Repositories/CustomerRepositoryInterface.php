<?php

namespace App\Domain\Auth\Repositories;

use App\Domain\Auth\CustomerSearchResult;
use App\Models\Customer;

/**
 * Data access for customers. The only layer that talks to the Customer Eloquent model.
 */
interface CustomerRepositoryInterface
{
    public function create(int $userId, string $fullName, string $phone, ?string $id = null): Customer;

    public function find(string $id): ?Customer;

    public function search(string $query, int $limit = 20): CustomerSearchResult;
}
