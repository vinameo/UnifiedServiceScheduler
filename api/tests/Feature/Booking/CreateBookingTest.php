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
}
