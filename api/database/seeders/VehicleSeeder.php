<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Seeder;

class VehicleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var list<array{owner_email: string, owner_name: string, licence_plate: string, vin: string|null, make: string, model: string, year: int}> $vehicles */
        $vehicles = [
            [
                'owner_email' => 'hung@example.test',
                'owner_name' => 'Nguyễn Văn Hùng',
                'licence_plate' => '51H-123.45',
                'vin' => 'RL4BT3F3X0M001234',
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2021,
            ],
            [
                'owner_email' => 'khanh@example.test',
                'owner_name' => 'Lâm Khánh',
                'licence_plate' => '59A-888.01',
                'vin' => null,
                'make' => 'Kia',
                'model' => 'Morning',
                'year' => 2019,
            ],
            [
                'owner_email' => 'khanh@example.test',
                'owner_name' => 'Lâm Khánh',
                'licence_plate' => '51K-246.80',
                'vin' => null,
                'make' => 'VinFast',
                'model' => 'VF e34',
                'year' => 2023,
            ],
        ];

        foreach ($vehicles as $vehicleData) {
            $customer = $this->resolveCustomer($vehicleData['owner_email'], $vehicleData['owner_name']);

            if (! $customer) {
                $this->command?->warn("Skipping vehicle {$vehicleData['licence_plate']}: customer {$vehicleData['owner_name']} not found.");

                continue;
            }

            $plateKey = Vehicle::normalizePlateKey($vehicleData['licence_plate']);

            Vehicle::updateOrCreate(
                ['plate_key' => $plateKey],
                [
                    'customer_id' => $customer->id,
                    'licence_plate' => $vehicleData['licence_plate'],
                    'vin' => $vehicleData['vin'],
                    'make' => $vehicleData['make'],
                    'model' => $vehicleData['model'],
                    'year' => $vehicleData['year'],
                ]
            );
        }
    }

    private function resolveCustomer(string $ownerEmail, string $ownerName): ?Customer
    {
        $user = User::where('email', $ownerEmail)->first();
        if ($user) {
            $customer = Customer::where('user_id', $user->id)->first();
            if ($customer) {
                return $customer;
            }
        }

        return Customer::where('full_name', $ownerName)->first();
    }
}
