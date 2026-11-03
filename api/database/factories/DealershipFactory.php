<?php

namespace Database\Factories;

use App\Models\Dealership;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dealership>
 */
class DealershipFactory extends Factory
{
    protected $model = Dealership::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'SGA-'.fake()->unique()->bothify('??##'),
            'name' => 'Saigon Auto',
            'branch_name' => 'District 7',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'phone' => '028 3812 3456',
        ];
    }
}
