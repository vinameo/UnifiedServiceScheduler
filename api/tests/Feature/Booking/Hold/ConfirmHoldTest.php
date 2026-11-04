<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Notifications\OutboxEventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ConfirmHoldTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_10_7_confirm_adds_the_vehicle(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Customer creates a hold without vehicle at 10:00
            $start = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $this->assertSame(1, $hold->version);
            $this->assertSame(AppointmentStatus::HELD, $hold->status);
            $this->assertNull($hold->vehicle_id);

            // Confirm with vehicle V and If-Match "1"
            $response = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(200);
            $response->assertHeader('ETag', '"2"');
            $response->assertJsonPath('status', AppointmentStatus::CONFIRMED->value);
            $response->assertJsonPath('expiresAt', null);
            $response->assertJsonPath('version', 2);
            $response->assertJsonPath('vehicle.id', $vehicle->id);

            // 3 active allocations in database (TECHNICIAN, BAY, VEHICLE)
            $allocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold->id)
                ->where('active', true)
                ->get();

            $this->assertCount(3, $allocations);
            $types = $allocations->pluck('resource_type')->all();
            $this->assertContains('TECHNICIAN', $types);
            $this->assertContains('BAY', $types);
            $this->assertContains('VEHICLE', $types);

            $vehicleAlloc = $allocations->firstWhere('resource_type', 'VEHICLE');
            $this->assertNotNull($vehicleAlloc);
            $this->assertSame($vehicle->id, $vehicleAlloc->resource_id);

            // 1 AppointmentConfirmed event across 3 channels (SMS, EMAIL, DMS)
            $outboxEvents = DB::table('outbox_messages')
                ->where('appointment_id', $hold->id)
                ->where('event_type', OutboxEventType::AppointmentConfirmed->value)
                ->get();

            $this->assertCount(3, $outboxEvents);
            $eventIds = $outboxEvents->pluck('event_id')->unique();
            $this->assertCount(1, $eventIds);

            // History CONFIRMED
            $events = DB::table('appointment_events')
                ->where('appointment_id', $hold->id)
                ->where('type', AppointmentEventType::CONFIRMED->value)
                ->get();

            $this->assertCount(1, $events);
            $firstEvent = $events->first();
            $this->assertNotNull($firstEvent);
            $this->assertSame(2, (int) $firstEvent->version);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
