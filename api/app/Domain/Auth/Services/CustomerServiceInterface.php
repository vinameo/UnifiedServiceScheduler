<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;

interface CustomerServiceInterface
{
    public function search(string $query, int $limit = 20): CustomerSearchResult;
}
