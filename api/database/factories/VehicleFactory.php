<?php

namespace Database\Factories;

use App\Models\Customer;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plate = '51H-'.fake()->unique()->numerify('###.##');

        return [
            'customer_id' => Customer::factory(),
            'licence_plate' => $plate,
            'plate_key' => Vehicle::normalizePlateKey($plate),
            'vin' => null,
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2021,
        ];
    }
}
