<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class HoldTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_10_1_hold_blocks_the_slot(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-02T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(201);
            $response->assertHeader('ETag', '"1"');
            $response->assertJsonPath('status', AppointmentStatus::HELD->value);
            $response->assertJsonPath('expiresAt', '2026-11-02T08:05:00+07:00');
            $response->assertJsonPath('vehicle', null);

            $appointmentId = $response->json('id');
            $this->assertNotNull($appointmentId);

            // Assert exactly 2 active allocations: TECHNICIAN and BAY, no VEHICLE
            $allocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentId)
                ->where('active', true)
                ->get();

            $this->assertCount(2, $allocations);
            $resourceTypes = $allocations->pluck('resource_type')->all();
            $this->assertContains('TECHNICIAN', $resourceTypes);
            $this->assertContains('BAY', $resourceTypes);
            $this->assertNotContains('VEHICLE', $resourceTypes);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_10_4_held_slot_is_not_free_for_others(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        ['user' => $customerUserA] = $world->customerWithVehicle();
        ['user' => $customerUserB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Customer A holds 10:00 (tomorrow 2026-11-03)
            $desiredStart = '2026-11-03T10:00:00+07:00';
            $holdResponse = $this->actingAs($customerUserA)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => $desiredStart,
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $holdResponse->assertStatus(201);
            $holdResponse->assertJsonPath('status', AppointmentStatus::HELD->value);

            // Customer B checks availability for tomorrow
            $availResponse = $this->actingAs($customerUserB)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-03"
            );

            $availResponse->assertOk();
            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots */
            $slots = $availResponse->json('data.slots');
            $slot10 = collect($slots)->first(fn (array $slot) => str_contains($slot['start'], '10:00:00'));
            $this->assertNotNull($slot10);
            $this->assertFalse($slot10['available']);

            // Customer B tries to book 10:00 -> 409 NO_AVAILABILITY
            $bookResponse = $this->actingAs($customerUserB)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $dealership->id,
                    'vehicleId' => $vehicleB->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => $desiredStart,
                    'mode' => 'CONFIRM',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $bookResponse->assertStatus(409);
            $this->assertSame('NO_AVAILABILITY', $bookResponse->json('code'));

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_10_5_new_hold_ends_the_old_one(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Given Customer A holds 10:00
            $response1 = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response1->assertStatus(201);
            $response1->assertJsonPath('status', AppointmentStatus::HELD->value);
            $hold1Id = $response1->json('id');
            $this->assertNotNull($hold1Id);

            // Active allocations for hold 1 exist (TECHNICIAN, BAY)
            $hold1ActiveCount = DB::table('resource_allocations')
                ->where('appointment_id', $hold1Id)
                ->where('active', true)
                ->count();
            $this->assertSame(2, $hold1ActiveCount);

            // When Customer A holds 11:00
            $response2 = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T11:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response2->assertStatus(201);
            $response2->assertJsonPath('status', AppointmentStatus::HELD->value);
            $hold2Id = $response2->json('id');
            $this->assertNotNull($hold2Id);
            $this->assertNotSame($hold1Id, $hold2Id);

            // Then 10:00 hold is EXPIRED with inactive allocations
            $hold1 = DB::table('appointments')->where('id', $hold1Id)->first();
            $this->assertNotNull($hold1);
            $this->assertSame(AppointmentStatus::EXPIRED->value, $hold1->status);

            $hold1ActiveAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold1Id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $hold1ActiveAllocations);

            $hold1InactiveAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold1Id)
                ->where('active', false)
                ->count();
            $this->assertSame(2, $hold1InactiveAllocations);

            // 11:00 hold is HELD with active allocations
            $hold2 = DB::table('appointments')->where('id', $hold2Id)->first();
            $this->assertNotNull($hold2);
            $this->assertSame(AppointmentStatus::HELD->value, $hold2->status);

            $hold2ActiveAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold2Id)
                ->where('active', true)
                ->count();
            $this->assertSame(2, $hold2ActiveAllocations);

            // Exactly one HELD appointment for Customer A
            $customerHeldCount = DB::table('appointments')
                ->where('customer_id', $customer->id)
                ->where('status', AppointmentStatus::HELD->value)
                ->count();
            $this->assertSame(1, $customerHeldCount);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_10_6_failed_new_hold_keeps_the_old_one(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        ['user' => $customerUserA] = $world->customerWithVehicle();
        ['user' => $customerUserB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Given Customer A holds 10:00
            $holdResponse = $this->actingAs($customerUserA)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $holdResponse->assertStatus(201);
            $holdResponse->assertJsonPath('status', AppointmentStatus::HELD->value);
            $holdId = $holdResponse->json('id');
            $this->assertNotNull($holdId);

            $holdBefore = DB::table('appointments')->where('id', $holdId)->first();
            $this->assertNotNull($holdBefore);
            $this->assertSame(1, (int) $holdBefore->version);

            // 11:00 is full: Customer B books 11:00 (taking An, the only active technician)
            $bookResponse = $this->actingAs($customerUserB)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $dealership->id,
                    'vehicleId' => $vehicleB->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T11:00:00+07:00',
                    'mode' => 'CONFIRM',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );
            $bookResponse->assertStatus(201);

            // When Customer A tries to hold 11:00
            $failedHoldResponse = $this->actingAs($customerUserA)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T11:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            // Then 409 NO_AVAILABILITY
            $failedHoldResponse->assertStatus(409);
            $this->assertSame('NO_AVAILABILITY', $failedHoldResponse->json('code'));

            // The 10:00 hold is still HELD, version unchanged
            $holdAfter = DB::table('appointments')->where('id', $holdId)->first();
            $this->assertNotNull($holdAfter);
            $this->assertSame(AppointmentStatus::HELD->value, $holdAfter->status);
            $this->assertSame((int) $holdBefore->version, (int) $holdAfter->version);

            // Allocations of 10:00 hold are still active
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $holdId)
                ->where('active', true)
                ->count();
            $this->assertSame(2, $activeAllocations);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_10_11_staff_cannot_hold(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'customerId' => $customer->id,
                    'vehicleId' => $vehicle->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(422);
            $response->assertJsonPath('code', 'HOLD_NOT_ALLOWED');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
