<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Notifications\OutboxEventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Carbon;
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

    public function test_AC_10_2_expired_hold_cannot_be_confirmed(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $this->travelTo($now);
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $this->travel(6)->minutes();
            CarbonImmutable::setTestNow(Carbon::getTestNow());

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

            $response->assertStatus(410);
            $this->assertSame('HOLD_EXPIRED', $response->json('code'));

            // Status is now EXPIRED, version 2
            $fresh = DB::table('appointments')->where('id', $hold->id)->first();
            $this->assertNotNull($fresh);
            $this->assertSame(AppointmentStatus::EXPIRED->value, $fresh->status);
            $this->assertSame(2, (int) $fresh->version);

            // All allocations for this hold are inactive
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold->id)
                ->where('active', true)
                ->get();

            $this->assertCount(0, $activeAllocations);

            $this->assertNoOverlappingAllocations();
        } finally {
            $this->travelBack();
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_10_8_vehicle_clash_keeps_the_hold(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $end = $start->addMinutes($service->duration_minutes);

            // Vehicle V already has another confirmed booking at 10:00
            $existingBooking = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $world->technician('Dũng'),
                bay: $world->bay('K2'),
                start: $start,
                end: $end,
                serviceType: $service,
                customer: $customer,
                vehicle: $vehicle,
                status: AppointmentStatus::CONFIRMED,
            );

            // Customer creates a hold at the same time using free technician An and Bay K1, without vehicle
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                technician: $world->technician('An'),
                bay: $world->bay('K1'),
                serviceType: $service,
            );

            $this->assertSame(1, $hold->version);
            $this->assertSame(AppointmentStatus::HELD, $hold->status);
            $this->assertNull($hold->vehicle_id);

            // Confirm with vehicle V
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

            $response->assertStatus(409);
            $this->assertSame('VEHICLE_ALREADY_BOOKED', $response->json('code'));
            $this->assertSame($existingBooking->id, $response->json('existingAppointment.id'));

            // Hold is still HELD, version 1, vehicle_id still null
            $freshHold = DB::table('appointments')->where('id', $hold->id)->first();
            $this->assertNotNull($freshHold);
            $this->assertSame(AppointmentStatus::HELD->value, $freshHold->status);
            $this->assertSame(1, (int) $freshHold->version);
            $this->assertNull($freshHold->vehicle_id);

            // Allocations for hold are still 2 active allocations (TECHNICIAN, BAY)
            $allocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold->id)
                ->where('active', true)
                ->get();

            $this->assertCount(2, $allocations);
            $types = $allocations->pluck('resource_type')->all();
            $this->assertContains('TECHNICIAN', $types);
            $this->assertContains('BAY', $types);
            $this->assertNotContains('VEHICLE', $types);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_INV_10_7_only_the_owner_confirms(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $customerUserB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $advisor = $world->advisor();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $holdA = AppointmentFactory::hold(
                customer: $customerA,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            // 1. Customer B tries to confirm Customer A's hold -> 404 APPOINTMENT_NOT_FOUND
            $responseB = $this->actingAs($customerUserB)->postJson(
                "/api/v1/appointments/{$holdA->id}/confirm",
                [
                    'vehicleId' => $vehicleB->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );
            $responseB->assertStatus(404);
            $this->assertSame('APPOINTMENT_NOT_FOUND', $responseB->json('code'));

            // 2. Advisor tries to confirm Customer A's hold -> 403 FORBIDDEN
            $responseAdvisor = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$holdA->id}/confirm",
                [
                    'vehicleId' => $vehicleA->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );
            $responseAdvisor->assertStatus(403);

            // 3. Customer A tries to confirm with Customer B's vehicle -> 404 VEHICLE_NOT_FOUND
            $responseVehicle = $this->actingAs($customerUserA)->postJson(
                "/api/v1/appointments/{$holdA->id}/confirm",
                [
                    'vehicleId' => $vehicleB->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );
            $responseVehicle->assertStatus(404);
            $this->assertSame('VEHICLE_NOT_FOUND', $responseVehicle->json('code'));

            // Hold A is untouched: still HELD, version 1
            $freshA = DB::table('appointments')->where('id', $holdA->id)->first();
            $this->assertNotNull($freshA);
            $this->assertSame(AppointmentStatus::HELD->value, $freshA->status);
            $this->assertSame(1, (int) $freshA->version);
            $this->assertNull($freshA->vehicle_id);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_boundary_and_replay(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        ['user' => $customerUserB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $t0 = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $expiresAt = CarbonImmutable::parse('2026-11-02 08:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($t0);

        try {
            // Customer A hold expiring at 08:05:00
            $holdA = AppointmentFactory::hold(
                customer: $customerA,
                start: CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh'),
                expiresAt: $expiresAt,
                vehicle: null,
                dealership: $world->dealership,
                technician: $world->technician('An'),
                bay: $world->bay('K1'),
                serviceType: $service,
            );

            // Customer B hold expiring at 08:05:00
            $holdB = AppointmentFactory::hold(
                customer: $customerB,
                start: CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh'),
                expiresAt: $expiresAt,
                vehicle: null,
                dealership: $world->dealership,
                technician: $world->technician('Dũng'),
                bay: $world->bay('K2'),
                serviceType: $service,
            );

            // 1. Confirm at 08:04:59 (1 second before expiry) -> 200
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:04:59', 'Asia/Ho_Chi_Minh'));
            $keyA = (string) Str::uuid();

            $responseA = $this->actingAs($customerUserA)->postJson(
                "/api/v1/appointments/{$holdA->id}/confirm",
                [
                    'vehicleId' => $vehicleA->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $keyA,
                ]
            );

            $responseA->assertStatus(200);
            $responseA->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $responseA->json('status'));
            $this->assertNull($responseA->json('expiresAt'));
            $this->assertSame(2, $responseA->json('version'));

            // 2. Another hold confirmed at exactly 08:05:00 -> 410 HOLD_EXPIRED
            CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:05:00', 'Asia/Ho_Chi_Minh'));
            $keyB = (string) Str::uuid();

            $responseB = $this->actingAs($customerUserB)->postJson(
                "/api/v1/appointments/{$holdB->id}/confirm",
                [
                    'vehicleId' => $vehicleB->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $keyB,
                ]
            );

            $responseB->assertStatus(410);
            $this->assertSame('HOLD_EXPIRED', $responseB->json('code'));

            $freshB = DB::table('appointments')->where('id', $holdB->id)->first();
            $this->assertNotNull($freshB);
            $this->assertSame(AppointmentStatus::EXPIRED->value, $freshB->status);
            $this->assertSame(2, (int) $freshB->version);

            // 3. Resend the first confirm with the same key -> the saved 200
            $responseReplay = $this->actingAs($customerUserA)->postJson(
                "/api/v1/appointments/{$holdA->id}/confirm",
                [
                    'vehicleId' => $vehicleA->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $keyA,
                ]
            );

            $responseReplay->assertStatus(200);
            $responseReplay->assertHeader('Idempotent-Replayed', 'true');
            $this->assertSame($responseA->json('id'), $responseReplay->json('id'));
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $responseReplay->json('status'));
            $this->assertSame(2, $responseReplay->json('version'));

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
