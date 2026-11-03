<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;

final readonly class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
    ) {}

    public function search(string $query, int $limit = 20): CustomerSearchResult
    {
        return $this->customerRepository->search($query, $limit);
    }
}
