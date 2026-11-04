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
}
