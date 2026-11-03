<?php

namespace Tests\Feature\MasterData;

use App\Models\Customer;
use App\Models\Vehicle;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleSchemaTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-01-08: given Vehicle 51H-123.45; when Insert vehicle 51h 12345;
     * two vehicles with NULL VIN; two with the same VIN;
     * then First: 23505; second: ok; third: 23505.
     */
    public function test_INV_01_5_plate_key_and_vin_are_unique(): void
    {
        $customer = Customer::factory()->create();

        // Given: Vehicle 51H-123.45 with VIN
        $firstVehicle = Vehicle::create([
            'customer_id' => $customer->id,
            'licence_plate' => '51H-123.45',
            'vin' => 'RL4BT3F3X0M001234',
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2021,
        ]);

        $this->assertSame('51H-123.45', $firstVehicle->licence_plate);
        $this->assertSame('51H12345', $firstVehicle->plate_key);
        $this->assertSame('RL4BT3F3X0M001234', $firstVehicle->vin);

        // When 1: Insert vehicle 51h 12345 (normalises to the same plate_key 51H12345)
        // Then 1: 23505 (unique violation)
        DB::statement('SAVEPOINT tc_01_08_plate');
        try {
            Vehicle::create([
                'customer_id' => $customer->id,
                'licence_plate' => '51h 12345',
                'vin' => null,
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2021,
            ]);
            DB::statement('RELEASE SAVEPOINT tc_01_08_plate');
            $this->fail('Expected inserting vehicle with same plate key to fail with 23505');
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT tc_01_08_plate');
            $this->assertSame('23505', $e->getCode());
        }

        // When 2: Two vehicles with NULL VIN
        // Then 2: ok
        $vehicleWithNullVin1 = Vehicle::create([
            'customer_id' => $customer->id,
            'licence_plate' => '59A-888.01',
            'vin' => null,
            'make' => 'Kia',
            'model' => 'Morning',
            'year' => 2019,
        ]);
        $vehicleWithNullVin2 = Vehicle::create([
            'customer_id' => $customer->id,
            'licence_plate' => '51K-246.80',
            'vin' => null,
            'make' => 'VinFast',
            'model' => 'VF e34',
            'year' => 2023,
        ]);

        $this->assertNull($vehicleWithNullVin1->vin);
        $this->assertNull($vehicleWithNullVin2->vin);
        $this->assertSame('59A88801', $vehicleWithNullVin1->plate_key);
        $this->assertSame('51K24680', $vehicleWithNullVin2->plate_key);

        // When 3: Two with the same VIN
        // Then 3: 23505 (unique violation)
        DB::statement('SAVEPOINT tc_01_08_vin');
        try {
            Vehicle::create([
                'customer_id' => $customer->id,
                'licence_plate' => '60A-111.22',
                'vin' => 'RL4BT3F3X0M001234', // Same VIN as firstVehicle
                'make' => 'Honda',
                'model' => 'City',
                'year' => 2022,
            ]);
            DB::statement('RELEASE SAVEPOINT tc_01_08_vin');
            $this->fail('Expected inserting vehicle with duplicate VIN to fail with 23505');
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT tc_01_08_vin');
            $this->assertSame('23505', $e->getCode());
        }
    }

    #[DataProvider('invalidRowsProvider')]
    public function test_DB_01_5_checks_reject_invalid_rows(Closure $insert, string $expectedSqlState): void
    {
        $customer = Customer::factory()->create();

        DB::statement('SAVEPOINT vehicle_check_test');
        try {
            $insert($customer->id);
            DB::statement('RELEASE SAVEPOINT vehicle_check_test');
            $this->fail("Expected query to fail with SQLSTATE {$expectedSqlState}, but it succeeded.");
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT vehicle_check_test');
            $this->assertSame($expectedSqlState, $e->getCode(), "Expected SQLSTATE {$expectedSqlState}, got {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<string, array{0: Closure(string): void, 1: string}>
     */
    public static function invalidRowsProvider(): array
    {
        return [
            'plate_key too short (<4 chars)' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51A',
                        'plate_key' => '51A',
                        'vin' => null,
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'plate_key too long (>12 chars)' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51A12345678901',
                        'plate_key' => '51A12345678901',
                        'vin' => null,
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'plate_key lowercase not allowed by check' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51a12345',
                        'plate_key' => '51a12345',
                        'vin' => null,
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'vin wrong length (16 chars)' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51H-111.11',
                        'plate_key' => '51H11111',
                        'vin' => 'RL4BT3F3X0M00123',
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'vin with letter I (not in VIN charset [A-HJ-NPR-Z0-9])' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51H-222.22',
                        'plate_key' => '51H22222',
                        'vin' => 'RL4BT3F3X0I001234',
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'year before 1950' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51H-333.33',
                        'plate_key' => '51H33333',
                        'vin' => null,
                        'make' => 'Ford',
                        'model' => 'Model T',
                        'year' => 1949,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'year after 2100' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customerId,
                        'licence_plate' => '51H-444.44',
                        'plate_key' => '51H44444',
                        'vin' => null,
                        'make' => 'Tesla',
                        'model' => 'Cybertruck',
                        'year' => 2101,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'customer_id foreign key does not exist' => [
                function (string $customerId): void {
                    DB::table('vehicles')->insert([
                        'id' => (string) Str::uuid(),
                        'customer_id' => (string) Str::uuid(),
                        'licence_plate' => '51H-555.55',
                        'plate_key' => '51H55555',
                        'vin' => null,
                        'make' => 'Toyota',
                        'model' => 'Vios',
                        'year' => 2021,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23503',
            ],
        ];
    }

    public function test_customer_deletion_is_restricted_when_vehicles_exist(): void
    {
        $vehicle = Vehicle::factory()->create();

        $this->expectException(QueryException::class);
        $this->expectExceptionCode('23503');

        $vehicle->customer->delete();
    }

    public function test_vehicle_belongs_to_customer_and_customer_has_many_vehicles(): void
    {
        $customer = Customer::factory()->create();
        $vehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);

        $this->assertTrue($vehicle->customer->is($customer));
        $this->assertTrue($customer->vehicles->contains($vehicle));
    }
}
