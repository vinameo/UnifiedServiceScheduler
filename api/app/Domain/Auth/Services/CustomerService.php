<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\CustomerSearchResult;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Booking\Exceptions\CustomerNotFound;
use App\Domain\Booking\Exceptions\PlateAlreadyRegistered;
use App\Domain\Booking\Exceptions\VehicleLimitReached;
use App\Domain\Booking\Exceptions\VinAlreadyRegistered;
use App\Domain\Booking\Repositories\VehicleRepositoryInterface;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
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

    /**
     * @return list<Vehicle>
     */
    public function myVehicles(User $user): array
    {
        $customer = $this->customerRepository->findByUserId($user->id);
        if ($customer === null) {
            return [];
        }

        return $this->vehicleRepository->findByCustomer($customer->id);
    }

    /**
     * @param  array{licencePlate: string, make: string, model: string, year: ?int, vin: ?string}  $data
     */
    public function addVehicle(User $user, array $data): Vehicle
    {
        $customer = $this->customerRepository->findByUserId($user->id);
        if ($customer === null) {
            throw new CustomerNotFound;
        }

        if ($this->vehicleRepository->countByCustomer($customer->id) >= 10) {
            throw new VehicleLimitReached;
        }

        $plate = trim($data['licencePlate']);
        $plateKey = Vehicle::normalizePlateKey($plate);
        $vin = isset($data['vin']) && trim($data['vin']) !== ''
            ? strtoupper(trim($data['vin']))
            : null;

        if ($this->vehicleRepository->findByPlateKey($plateKey) !== null) {
            throw new PlateAlreadyRegistered($plate);
        }

        if ($vin !== null && $this->vehicleRepository->findByVin($vin) !== null) {
            throw new VinAlreadyRegistered($vin);
        }

        try {
            return $this->vehicleRepository->create([
                'customer_id' => $customer->id,
                'licence_plate' => $plate,
                'plate_key' => $plateKey,
                'vin' => $vin,
                'make' => trim($data['make']),
                'model' => trim($data['model']),
                'year' => $data['year'] ?? null,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                $msg = $e->getMessage();
                if (str_contains($msg, 'vehicles_plate_key_unique') || str_contains($msg, 'plate_key')) {
                    throw new PlateAlreadyRegistered($plate);
                }
                if (str_contains($msg, 'vehicles_vin_unique') || str_contains($msg, 'vin')) {
                    throw new VinAlreadyRegistered((string) $vin);
                }
            }
            throw $e;
        }
    }
}
