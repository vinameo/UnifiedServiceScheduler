<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Booking\Exceptions\CustomerNotFound;
use App\Domain\Booking\Repositories\VehicleRepositoryInterface;
use App\Models\Vehicle;
use Illuminate\Support\Str;

final readonly class CustomerService implements CustomerServiceInterface
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private VehicleRepositoryInterface $vehicleRepository,
    ) {}

    public function search(string $query, int $limit = 20): CustomerSearchResult
    {
        return $this->customerRepository->search($query, $limit);
    }

    /**
     * @return list<Vehicle>
     */
    public function vehicles(string $customerId): array
    {
        if (! Str::isUuid($customerId)) {
            throw new CustomerNotFound;
        }

        $customer = $this->customerRepository->find($customerId);
        if ($customer === null) {
            throw new CustomerNotFound;
        }

        return $this->vehicleRepository->findByCustomer($customerId);
    }
}
