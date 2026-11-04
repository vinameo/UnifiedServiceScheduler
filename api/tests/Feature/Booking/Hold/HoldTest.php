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
