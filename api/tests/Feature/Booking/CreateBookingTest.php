<?php

namespace Tests\Feature\Booking;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class CreateBookingTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_02_1_advisor_books_and_gets_confirmed_appointment(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $desiredStart = '2026-11-02T09:00:00+07:00';
            $key = (string) Str::uuid();

            $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $oilChange->id,
                'desiredStart' => $desiredStart,
            ], [
                'Idempotency-Key' => $key,
            ]);

            $response->assertStatus(201)
                ->assertHeader('Location', '/api/v1/appointments/'.$response->json('id'))
                ->assertHeader('ETag', '"1"')
                ->assertJsonPath('status', 'CONFIRMED')
                ->assertJsonPath('start', '2026-11-02T09:00:00+07:00')
                ->assertJsonPath('end', '2026-11-02T10:00:00+07:00')
                ->assertJsonPath('serviceType.code', 'OIL_CHANGE')
                ->assertJsonPath('vehicle.id', $vehicle->id)
                ->assertJsonPath('customer.id', $customer->id);

            $this->assertNotNull($response->json('technician.id'));
            $this->assertNotNull($response->json('bay.id'));
            $this->assertNotNull($response->json('createdAt'));
            $this->assertNotNull($response->json('reference'));

            $this->assertDatabaseHas('appointments', [
                'id' => $response->json('id'),
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_type_id' => $oilChange->id,
                'status' => 'CONFIRMED',
                'version' => 1,
            ]);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_8_missing_idempotency_key(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => '2026-11-02T09:00:00+07:00',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_AC_02_14_foreman_and_admin_cannot_book(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $admin = $world->admin();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $payload = [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => '2026-11-02T09:00:00+07:00',
        ];

        $responseForeman = $this->actingAs($foreman)->postJson('/api/v1/appointments', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $responseForeman->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        $responseAdmin = $this->actingAs($admin)->postJson('/api/v1/appointments', $payload, [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $responseAdmin->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_AC_02_16_confirmed_booking_writes_outbox(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00+07:00',
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $response->assertStatus(201);
            $appointmentId = $response->json('id');

            $outboxRows = DB::table('outbox_messages')
                ->where('appointment_id', $appointmentId)
                ->get();

            $this->assertCount(3, $outboxRows);
            $eventIds = $outboxRows->pluck('event_id')->unique();
            $this->assertCount(1, $eventIds);
            $firstOutbox = $outboxRows->first();
            $this->assertNotNull($firstOutbox);
            $this->assertSame('AppointmentConfirmed', $firstOutbox->event_type);

            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentId)
                ->where('active', true)
                ->get();

            $this->assertCount(3, $activeAllocations);
            $types = $activeAllocations->pluck('resource_type')->sort()->values()->all();
            $this->assertSame(['BAY', 'TECHNICIAN', 'VEHICLE'], $types);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_04_3_replay_through_the_real_endpoint(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $key = (string) Str::uuid();
            $payload = [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00+07:00',
            ];

            // 1. Initial request with K
            $response1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', $payload, [
                'Idempotency-Key' => $key,
            ]);

            $response1->assertStatus(201)
                ->assertHeaderMissing('Idempotent-Replayed');

            $appointmentId = $response1->json('id');

            // 2. Resend identical request with same key K
            $response2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', $payload, [
                'Idempotency-Key' => $key,
            ]);

            $response2->assertStatus(201)
                ->assertHeader('Idempotent-Replayed', 'true')
                ->assertJsonPath('id', $appointmentId);

            // Assert exactly 1 appointment and 3 outbox rows (1 event)
            $this->assertSame(1, DB::table('appointments')->count());
            $this->assertSame(3, DB::table('outbox_messages')->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_2_start_in_past(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00+07:00',
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('code', 'START_IN_PAST');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_3_end_after_closing(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T16:30:00+07:00',
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('code', 'OUTSIDE_OPENING_HOURS')
                ->assertJsonPath('closesAt', '17:00')
                ->assertJsonPath('latestStart', '2026-11-02T16:00:00+07:00');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_11_offset_and_step_required(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 1. Off 15-minute step
            $res1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:10:00+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res1->assertStatus(400)->assertJsonValidationErrors('desiredStart');

            // 2. Missing offset
            $res2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res2->assertStatus(400)->assertJsonValidationErrors('desiredStart');

            // 3. Seconds not zero
            $res3 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:30+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res3->assertStatus(400)->assertJsonValidationErrors('desiredStart');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_13_closed_day(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-08T09:00:00+07:00', // Sunday
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $response->assertStatus(422)
                ->assertJsonPath('code', 'OUTSIDE_OPENING_HOURS')
                ->assertJsonPath('opensAt', null);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_INV_02_4_local_day_from_dealership_zone(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-01 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 2026-11-02T01:00:00Z = 08:00 Monday local (valid, opens 08:00) -> 201
            $res1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T01:00:00Z',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res1->assertStatus(201);

            // 2026-11-01T23:30:00Z = 06:30 Monday local (before opening 08:00) -> 422 OUTSIDE_OPENING_HOURS
            ['customer' => $customer2, 'vehicle' => $vehicle2] = $world->customerWithVehicle();
            $res2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer2->id,
                'vehicleId' => $vehicle2->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-01T23:30:00Z',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res2->assertStatus(422)->assertJsonPath('code', 'OUTSIDE_OPENING_HOURS');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_02_12_ninety_day_horizon(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        // Allow Sunday opening for testing exactly 90 days (2027-01-31 is Sunday)
        DB::table('dealership_opening_hours')->insert([
            'id' => (string) Str::uuid(),
            'dealership_id' => $world->dealership->id,
            'weekday' => 7,
            'opens_at' => '08:00:00',
            'closes_at' => '17:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 91 days ahead (2027-02-01) -> 422 START_TOO_FAR_AHEAD (maxDate 2027-01-31)
            $res1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2027-02-01T09:00:00+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);

            $res1->assertStatus(422)
                ->assertJsonPath('code', 'START_TOO_FAR_AHEAD')
                ->assertJsonPath('maxDate', '2027-01-31');

            // 90 days ahead (2027-01-31) -> 201
            ['customer' => $customer2, 'vehicle' => $vehicle2] = $world->customerWithVehicle();
            $res2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer2->id,
                'vehicleId' => $vehicle2->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2027-01-31T09:00:00+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);

            $res2->assertStatus(201);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_boundaries_now_and_closing_time(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Exactly 09:00:00 (equal to now) -> 422 START_IN_PAST
            $res1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T09:00:00+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res1->assertStatus(422)->assertJsonPath('code', 'START_IN_PAST');

            // Book OIL at 16:00 (duration 60 min -> ends exactly 17:00 at closing) -> 201
            ['customer' => $customer2, 'vehicle' => $vehicle2] = $world->customerWithVehicle();
            $res2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer2->id,
                'vehicleId' => $vehicle2->id,
                'serviceTypeId' => $service->id,
                'desiredStart' => '2026-11-02T16:00:00+07:00',
            ], ['Idempotency-Key' => (string) Str::uuid()]);
            $res2->assertStatus(201);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
