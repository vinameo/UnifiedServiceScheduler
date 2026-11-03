<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\ResourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class ExclusionConstraintTest extends TestCase
{
    use DatabaseTruncation;

    public function test_INV_04_1_db_rejects_active_overlap_only(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $bay = $world->bay('K1');
        $tech = $world->technician('An');
        $service = $world->serviceType('OIL_CHANGE');

        $baseDate = CarbonImmutable::parse('2026-11-10 00:00:00', 'UTC');
        $t0900 = $baseDate->setTime(9, 0, 0)->toIso8601String();
        $t0930 = $baseDate->setTime(9, 30, 0)->toIso8601String();
        $t0959 = $baseDate->setTime(9, 59, 0)->toIso8601String();
        $t1000 = $baseDate->setTime(10, 0, 0)->toIso8601String();
        $t1030 = $baseDate->setTime(10, 30, 0)->toIso8601String();
        $t1100 = $baseDate->setTime(11, 0, 0)->toIso8601String();

        $appointmentId1 = (string) Str::uuid();
        $appointmentId2 = (string) Str::uuid();
        $appointmentId3 = (string) Str::uuid();
        $appointmentId4 = (string) Str::uuid();

        foreach ([$appointmentId1, $appointmentId2, $appointmentId3, $appointmentId4] as $idx => $aptId) {
            DB::table('appointments')->insert([
                'id' => $aptId,
                'reference' => 'APT-20261110-000'.($idx + 1),
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_type_id' => $service->id,
                'technician_id' => $tech->id,
                'bay_id' => $bay->id,
                'status' => AppointmentStatus::CONFIRMED->value,
                'start_at' => $t0900,
                'end_at' => $t1000,
                'version' => 1,
                'source' => 'CUSTOMER',
                'created_by_user_id' => $user->id,
                'created_at' => $baseDate,
                'updated_at' => $baseDate,
            ]);
        }

        // 1. Initial allocation: Active BAY allocation K1 09:00–10:00
        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $appointmentId1,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::BAY->value,
            'resource_id' => $bay->id,
            'start' => $t0900,
            'end' => $t1000,
            'created_at' => $baseDate->toIso8601String(),
        ]);

        // 2. Insert by hand K1 09:59–10:30 active -> throws 23P01
        try {
            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => (string) Str::uuid(),
                'appointment_id' => $appointmentId2,
                'dealership_id' => $world->dealership->id,
                'resource_type' => ResourceType::BAY->value,
                'resource_id' => $bay->id,
                'start' => $t0959,
                'end' => $t1030,
                'created_at' => $baseDate->toIso8601String(),
            ]);
            $this->fail('Expected QueryException with code 23P01 on overlapping active allocation');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->getCode());
        }

        // 3. Insert K1 10:00–11:00 active (adjacent half-open) -> succeeds
        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $appointmentId3,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::BAY->value,
            'resource_id' => $bay->id,
            'start' => $t1000,
            'end' => $t1100,
            'created_at' => $baseDate->toIso8601String(),
        ]);

        // 4. Insert K1 09:30–10:30 inactive (overlaps, but active = false) -> succeeds
        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at, deactivated_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), false, :created_at, :deactivated_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $appointmentId4,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::BAY->value,
            'resource_id' => $bay->id,
            'start' => $t0930,
            'end' => $t1030,
            'created_at' => $baseDate->toIso8601String(),
            'deactivated_at' => $baseDate->toIso8601String(),
        ]);

        $this->assertSame(3, DB::table('resource_allocations')->count());
        $this->assertSame(2, DB::table('resource_allocations')->where('active', true)->count());
        $this->assertSame(1, DB::table('resource_allocations')->where('active', false)->count());
    }
}
