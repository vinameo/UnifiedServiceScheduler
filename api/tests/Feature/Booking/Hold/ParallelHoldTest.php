<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelHoldTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_INV_10_2_one_hold_per_customer_in_parallel(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        // Choose a target Wednesday well into the future during opening hours
        $targetWednesday = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY);
        $desiredStart1 = $targetWednesday->setTime(10, 0, 0)->toIso8601String();
        $desiredStart2 = $targetWednesday->setTime(11, 0, 0)->toIso8601String();

        $dealershipId = $world->dealership->id;
        $serviceTypeId = $service->id;

        // Given Customer A has no hold
        $initialHeldCount = DB::table('appointments')
            ->where('customer_id', $customer->id)
            ->where('status', AppointmentStatus::HELD->value)
            ->count();
        $this->assertSame(0, $initialHeldCount);

        // When 2 processes hold 10:00 and 11:00 for A at once
        $results = Parallel::run(2, function (int $i) use ($customerUser, $dealershipId, $serviceTypeId, $desiredStart1, $desiredStart2) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/appointments',
                'user' => $customerUser,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'serviceTypeId' => $serviceTypeId,
                    'desiredStart' => $i === 0 ? $desiredStart1 : $desiredStart2,
                    'mode' => 'HOLD',
                ],
            ];
        }, delaySeconds: 0.5);

        $this->assertCount(2, $results);

        // Then both responses 201
        foreach ($results as $i => $res) {
            $this->assertSame(
                201,
                $res['status'],
                "Expected 201 for process {$i}, got {$res['status']}. Body: ".json_encode($res['body'])
            );
            $this->assertSame('HELD', $res['body']['status'] ?? null);
        }

        // Exactly one HELD remains
        $appointments = DB::table('appointments')
            ->where('customer_id', $customer->id)
            ->get();
        $this->assertCount(2, $appointments);

        $held = $appointments->where('status', AppointmentStatus::HELD->value);
        $expired = $appointments->where('status', AppointmentStatus::EXPIRED->value);

        $this->assertCount(1, $held, 'Exactly one HELD appointment must remain');
        $this->assertCount(1, $expired, 'Exactly one EXPIRED appointment must remain');

        $heldAppt = $held->first();
        $expiredAppt = $expired->first();
        $this->assertNotNull($heldAppt);
        $this->assertNotNull($expiredAppt);

        // The held appointment has 2 active allocations (TECHNICIAN, BAY)
        $heldActiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $heldAppt->id)
            ->where('active', true)
            ->count();
        $this->assertSame(2, $heldActiveAllocations);

        // The expired appointment has 0 active allocations and 2 inactive allocations
        $expiredActiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $expiredAppt->id)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $expiredActiveAllocations);

        $expiredInactiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $expiredAppt->id)
            ->where('active', false)
            ->count();
        $this->assertSame(2, $expiredInactiveAllocations);

        $this->assertNoOverlappingAllocations();
    }

    public function test_D_10_4_parallel_holds_with_an_existing_hold(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        // Choose a target Wednesday well into the future during opening hours
        $targetWednesday = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY);
        $desiredStart0 = $targetWednesday->setTime(9, 0, 0)->toIso8601String();
        $desiredStart1 = $targetWednesday->setTime(10, 0, 0)->toIso8601String();
        $desiredStart2 = $targetWednesday->setTime(11, 0, 0)->toIso8601String();

        $dealershipId = $world->dealership->id;
        $serviceTypeId = $service->id;

        // Given Customer A holds 09:00 (v1)
        $initialHoldResponse = $this->actingAs($customerUser)->postJson(
            '/api/v1/appointments',
            [
                'dealershipId' => $dealershipId,
                'serviceTypeId' => $serviceTypeId,
                'desiredStart' => $desiredStart0,
                'mode' => 'HOLD',
            ],
            ['Idempotency-Key' => (string) Str::uuid()]
        );

        $initialHoldResponse->assertStatus(201);
        $initialHoldId = $initialHoldResponse->json('id');
        $this->assertNotNull($initialHoldId);

        $initialHold = DB::table('appointments')->where('id', $initialHoldId)->first();
        $this->assertNotNull($initialHold);
        $this->assertSame(1, (int) $initialHold->version);
        $this->assertSame(AppointmentStatus::HELD->value, $initialHold->status);

        // When 2 processes hold 10:00 and 11:00 for A at once
        $results = Parallel::run(2, function (int $i) use ($customerUser, $dealershipId, $serviceTypeId, $desiredStart1, $desiredStart2) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/appointments',
                'user' => $customerUser,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'serviceTypeId' => $serviceTypeId,
                    'desiredStart' => $i === 0 ? $desiredStart1 : $desiredStart2,
                    'mode' => 'HOLD',
                ],
            ];
        }, delaySeconds: 0.5);

        $this->assertCount(2, $results);

        // Then both 201 (never 412)
        foreach ($results as $i => $res) {
            $this->assertSame(
                201,
                $res['status'],
                "Expected 201 for process {$i}, got {$res['status']}. Body: ".json_encode($res['body'])
            );
            $this->assertSame('HELD', $res['body']['status'] ?? null);
        }

        // Exactly one HELD remains
        $appointments = DB::table('appointments')
            ->where('customer_id', $customer->id)
            ->get();
        $this->assertCount(3, $appointments);

        $held = $appointments->where('status', AppointmentStatus::HELD->value);
        $expired = $appointments->where('status', AppointmentStatus::EXPIRED->value);

        $this->assertCount(1, $held, 'Exactly one HELD appointment must remain');
        $this->assertCount(2, $expired, 'Exactly two EXPIRED appointments must remain');

        // The 09:00 hold and the earlier new hold are EXPIRED with inactive allocations
        $refreshedInitial = DB::table('appointments')->where('id', $initialHoldId)->first();
        $this->assertNotNull($refreshedInitial);
        $this->assertSame(AppointmentStatus::EXPIRED->value, $refreshedInitial->status);
        $this->assertSame(2, (int) $refreshedInitial->version);

        $initialActiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $initialHoldId)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $initialActiveAllocations);

        $initialInactiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $initialHoldId)
            ->where('active', false)
            ->count();
        $this->assertSame(2, $initialInactiveAllocations);

        // The held appointment has 2 active allocations
        $heldAppt = $held->first();
        $this->assertNotNull($heldAppt);
        $heldActiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $heldAppt->id)
            ->where('active', true)
            ->count();
        $this->assertSame(2, $heldActiveAllocations);

        // The other expired appointment also has 0 active and 2 inactive allocations
        $otherExpired = $expired->firstWhere('id', '!=', $initialHoldId);
        $this->assertNotNull($otherExpired);
        $otherExpiredActive = DB::table('resource_allocations')
            ->where('appointment_id', $otherExpired->id)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $otherExpiredActive);

        $otherExpiredInactive = DB::table('resource_allocations')
            ->where('appointment_id', $otherExpired->id)
            ->where('active', false)
            ->count();
        $this->assertSame(2, $otherExpiredInactive);

        $this->assertNoOverlappingAllocations();
    }
}
