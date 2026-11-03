<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\ExclusionViolationParser;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class ExclusionViolationParserTest extends TestCase
{
    use DatabaseTruncation;

    public function test_D_04_17_parses_real_23P01_for_technician(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-12 09:00:00', 'UTC');

        $apt1 = (string) Str::uuid();
        $apt2 = (string) Str::uuid();

        foreach ([$apt1, $apt2] as $idx => $id) {
            DB::table('appointments')->insert([
                'id' => $id,
                'reference' => 'APT-20261112-000'.($idx + 1),
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
        }

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $apt1,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::TECHNICIAN->value,
            'resource_id' => $tech->id,
            'start' => $now->toIso8601String(),
            'end' => $now->addHour()->toIso8601String(),
            'created_at' => $now->toIso8601String(),
        ]);

        try {
            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apt2,
                'dealership_id' => $world->dealership->id,
                'resource_type' => ResourceType::TECHNICIAN->value,
                'resource_id' => $tech->id,
                'start' => $now->toIso8601String(),
                'end' => $now->addHour()->toIso8601String(),
                'created_at' => $now->toIso8601String(),
            ]);
            $this->fail('Expected 23P01 exclusion violation for TECHNICIAN');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->getCode());
            $this->assertSame(ResourceType::TECHNICIAN, ExclusionViolationParser::parse($e));
        }
    }

    public function test_D_04_17_parses_real_23P01_for_bay(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-12 09:00:00', 'UTC');

        $apt1 = (string) Str::uuid();
        $apt2 = (string) Str::uuid();

        foreach ([$apt1, $apt2] as $idx => $id) {
            DB::table('appointments')->insert([
                'id' => $id,
                'reference' => 'APT-20261112-001'.($idx + 1),
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
        }

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $apt1,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::BAY->value,
            'resource_id' => $bay->id,
            'start' => $now->toIso8601String(),
            'end' => $now->addHour()->toIso8601String(),
            'created_at' => $now->toIso8601String(),
        ]);

        try {
            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apt2,
                'dealership_id' => $world->dealership->id,
                'resource_type' => ResourceType::BAY->value,
                'resource_id' => $bay->id,
                'start' => $now->toIso8601String(),
                'end' => $now->addHour()->toIso8601String(),
                'created_at' => $now->toIso8601String(),
            ]);
            $this->fail('Expected 23P01 exclusion violation for BAY');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->getCode());
            $this->assertSame(ResourceType::BAY, ExclusionViolationParser::parse($e));
        }
    }

    public function test_D_04_17_parses_real_23P01_for_vehicle(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-12 09:00:00', 'UTC');

        $apt1 = (string) Str::uuid();
        $apt2 = (string) Str::uuid();

        foreach ([$apt1, $apt2] as $idx => $id) {
            DB::table('appointments')->insert([
                'id' => $id,
                'reference' => 'APT-20261112-002'.($idx + 1),
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
        }

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $apt1,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::VEHICLE->value,
            'resource_id' => $vehicle->id,
            'start' => $now->toIso8601String(),
            'end' => $now->addHour()->toIso8601String(),
            'created_at' => $now->toIso8601String(),
        ]);

        try {
            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apt2,
                'dealership_id' => $world->dealership->id,
                'resource_type' => ResourceType::VEHICLE->value,
                'resource_id' => $vehicle->id,
                'start' => $now->toIso8601String(),
                'end' => $now->addHour()->toIso8601String(),
                'created_at' => $now->toIso8601String(),
            ]);
            $this->fail('Expected 23P01 exclusion violation for VEHICLE');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->getCode());
            $this->assertSame(ResourceType::VEHICLE, ExclusionViolationParser::parse($e));
        }
    }

    public function test_R_04_2_unknown_string_defaults_to_technician_and_logs_warning(): void
    {
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Unable to parse resource_type')
                    && isset($context['message']);
            });

        $dummyPdoException = new \PDOException('SQLSTATE[23P01]: Exclusion violation without clear detail string');
        $exception = new QueryException('pgsql', 'INSERT INTO resource_allocations', [], $dummyPdoException);

        $result = ExclusionViolationParser::parse($exception);
        $this->assertSame(ResourceType::TECHNICIAN, $result);
    }
}
