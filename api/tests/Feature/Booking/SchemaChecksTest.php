<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\ResourceType;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\World;
use Tests\TestCase;

class SchemaChecksTest extends TestCase
{
    use DatabaseTruncation;

    #[DataProvider('invalidSchemaCasesProvider')]
    public function test_schema_checks(Closure $action, string $expectedSqlState): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $bay = $world->bay('K1');
        $tech = $world->technician('An');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC');

        try {
            $action($world, $user, $customer, $vehicle, $bay, $tech, $service, $now);
            $this->fail("Expected QueryException with SQLSTATE {$expectedSqlState}");
        } catch (QueryException $e) {
            $this->assertSame($expectedSqlState, $e->getCode(), "Expected SQLSTATE {$expectedSqlState}, got {$e->getCode()} ({$e->getMessage()})");
        }
    }

    /**
     * @return array<string, array{0: Closure, 1: string}>
     */
    public static function invalidSchemaCasesProvider(): array
    {
        return [
            'period (]' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    $aptId = (string) Str::uuid();
                    DB::table('appointments')->insert([
                        'id' => $aptId,
                        'reference' => 'APT-20261105-0001',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'start_at' => $now,
                        'end_at' => $now->addHour(),
                        'version' => 1,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::statement(<<<'SQL'
                        INSERT INTO resource_allocations (
                            id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                        ) VALUES (
                            :id, :appointment_id, :dealership_id, :resource_type, :resource_id,
                            tstzrange('2026-11-05 09:00:00Z', '2026-11-05 10:00:00Z', '(]'),
                            true, :created_at
                        )
                    SQL, [
                        'id' => (string) Str::uuid(),
                        'appointment_id' => $aptId,
                        'dealership_id' => $world->dealership->id,
                        'resource_type' => ResourceType::BAY->value,
                        'resource_id' => $bay->id,
                        'created_at' => $now->toIso8601String(),
                    ]);
                },
                '23514',
            ],
            'empty period' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    $aptId = (string) Str::uuid();
                    DB::table('appointments')->insert([
                        'id' => $aptId,
                        'reference' => 'APT-20261105-0002',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'start_at' => $now,
                        'end_at' => $now->addHour(),
                        'version' => 1,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    DB::statement(<<<'SQL'
                        INSERT INTO resource_allocations (
                            id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                        ) VALUES (
                            :id, :appointment_id, :dealership_id, :resource_type, :resource_id,
                            'empty'::tstzrange,
                            true, :created_at
                        )
                    SQL, [
                        'id' => (string) Str::uuid(),
                        'appointment_id' => $aptId,
                        'dealership_id' => $world->dealership->id,
                        'resource_type' => ResourceType::BAY->value,
                        'resource_id' => $bay->id,
                        'created_at' => $now->toIso8601String(),
                    ]);
                },
                '23514',
            ],
            'HELD without expires_at' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    DB::table('appointments')->insert([
                        'id' => (string) Str::uuid(),
                        'reference' => 'APT-20261105-0003',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::HELD->value,
                        'start_at' => $now,
                        'end_at' => $now->addHour(),
                        'expires_at' => null,
                        'version' => 1,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                },
                '23514',
            ],
            'CONFIRMED without vehicle' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    DB::table('appointments')->insert([
                        'id' => (string) Str::uuid(),
                        'reference' => 'APT-20261105-0004',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => null,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'start_at' => $now,
                        'end_at' => $now->addHour(),
                        'version' => 1,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                },
                '23514',
            ],
            'version = 0' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    DB::table('appointments')->insert([
                        'id' => (string) Str::uuid(),
                        'reference' => 'APT-20261105-0005',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'start_at' => $now,
                        'end_at' => $now->addHour(),
                        'version' => 0,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                },
                '23514',
            ],
            'two active allocations of one type for one appointment' => [
                function ($world, $user, $customer, $vehicle, $bay, $tech, $service, $now) {
                    $aptId = (string) Str::uuid();
                    DB::table('appointments')->insert([
                        'id' => $aptId,
                        'reference' => 'APT-20261105-0006',
                        'dealership_id' => $world->dealership->id,
                        'customer_id' => $customer->id,
                        'vehicle_id' => $vehicle->id,
                        'service_type_id' => $service->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => AppointmentStatus::CONFIRMED->value,
                        'start_at' => $now,
                        'end_at' => $now->addHours(4),
                        'version' => 1,
                        'source' => 'CUSTOMER',
                        'created_by_user_id' => $user->id,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    // Allocation 1: tech An 09:00 - 10:00 active
                    DB::statement(<<<'SQL'
                        INSERT INTO resource_allocations (
                            id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                        ) VALUES (
                            :id, :appointment_id, :dealership_id, :resource_type, :resource_id,
                            tstzrange('2026-11-05 09:00:00Z', '2026-11-05 10:00:00Z', '[)'),
                            true, :created_at
                        )
                    SQL, [
                        'id' => (string) Str::uuid(),
                        'appointment_id' => $aptId,
                        'dealership_id' => $world->dealership->id,
                        'resource_type' => ResourceType::TECHNICIAN->value,
                        'resource_id' => $tech->id,
                        'created_at' => $now->toIso8601String(),
                    ]);

                    // Allocation 2: tech Dung 11:00 - 12:00 active (same appointment, same resource_type = TECHNICIAN)
                    $techDung = $world->technician('Dũng');
                    DB::statement(<<<'SQL'
                        INSERT INTO resource_allocations (
                            id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                        ) VALUES (
                            :id, :appointment_id, :dealership_id, :resource_type, :resource_id,
                            tstzrange('2026-11-05 11:00:00Z', '2026-11-05 12:00:00Z', '[)'),
                            true, :created_at
                        )
                    SQL, [
                        'id' => (string) Str::uuid(),
                        'appointment_id' => $aptId,
                        'dealership_id' => $world->dealership->id,
                        'resource_type' => ResourceType::TECHNICIAN->value,
                        'resource_id' => $techDung->id,
                        'created_at' => $now->toIso8601String(),
                    ]);
                },
                '23505',
            ],
        ];
    }

    public function test_EXPIRED_and_CANCELLED_without_vehicle_are_allowed(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer] = $world->customerWithVehicle();
        $bay = $world->bay('K1');
        $tech = $world->technician('An');
        $service = $world->serviceType('OIL_CHANGE');
        $now = CarbonImmutable::parse('2026-11-05 09:00:00', 'UTC');

        // EXPIRED without vehicle
        DB::table('appointments')->insert([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261105-0007',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => null,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::EXPIRED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // CANCELLED without vehicle
        DB::table('appointments')->insert([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261105-0008',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => null,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CANCELLED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->assertSame(2, DB::table('appointments')->whereNull('vehicle_id')->count());
    }
}
