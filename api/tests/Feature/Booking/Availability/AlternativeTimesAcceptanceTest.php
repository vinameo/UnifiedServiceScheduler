<?php

namespace Tests\Feature\Booking\Availability;

use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class AlternativeTimesAcceptanceTest extends TestCase
{
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-06-20 / M1:
     * Given Advisor Minh Anh at Saigon Auto D7 (clock at 2026-11-02 08:00 +07:00);
     * when Advisor books an EV battery check at 09:00 tomorrow (Ngô Bảo Em is the only qualified technician);
     * then Booking succeeds with 201 Created.
     */
    public function test_TC_06_20_M1_advisor_books_ev_battery_check_tomorrow(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh'));

        $world = World::saigonD7();
        $dealership = $world->dealership;
        $advisor = $world->advisor();
        $evBattery = $world->serviceType('EV_BATTERY');

        $customerUser = User::factory()->customer()->create(['email' => 'm1_customer@example.test']);
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle($customerUser, [
            'plate_key' => '51M11111',
            'licence_plate' => '51M-111.11',
        ]);

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $evBattery->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(201);
        $appointmentId = $response->json('id');
        $this->assertNotNull($appointmentId);

        /** @var Appointment $appointment */
        $appointment = Appointment::findOrFail($appointmentId);
        $this->assertSame('CONFIRMED', $appointment->status->value);
        $this->assertSame($world->technician('Ngô Bảo Em')->id, $appointment->technician_id);
        $this->assertSame($world->bay('KEV')->id, $appointment->bay_id);
    }

    /**
     * TC-06-20 / M2:
     * Given EV battery check booked tomorrow 09:00–10:30;
     * when Requesting availability for EV battery check tomorrow (90-minute service);
     * then Slots 08:00–10:15 are unavailable (NO_TECHNICIAN_AVAILABLE) and 10:30 is available.
     */
    public function test_TC_06_20_M2_overlapping_starts_struck_through_1030_free(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh'));

        $world = World::saigonD7();
        $dealership = $world->dealership;
        $advisor = $world->advisor();
        $evBattery = $world->serviceType('EV_BATTERY');

        $customerUser = User::factory()->customer()->create(['email' => 'm2_customer@example.test']);
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle($customerUser, [
            'plate_key' => '51M22222',
            'licence_plate' => '51M-222.22',
        ]);

        // Step M1: Book 09:00 tomorrow
        $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $evBattery->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $bookResponse->assertStatus(201);

        // Step M2: Check availability tomorrow
        $availResponse = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?date=2026-11-03&serviceTypeId={$evBattery->id}"
        );
        $availResponse->assertOk();

        /** @var list<array{start: string, available: bool, reason: string|null}> $slots */
        $slots = $availResponse->json('data.slots');
        $slotMap = [];
        foreach ($slots as $slot) {
            $timePart = substr($slot['start'], 11, 5);
            $slotMap[$timePart] = $slot;
        }

        // 08:00 to 10:15 every 15 minutes overlaps 09:00-10:30 with 90-min duration
        $overlappingStarts = ['08:00', '08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45', '10:00', '10:15'];
        foreach ($overlappingStarts as $time) {
            $this->assertArrayHasKey($time, $slotMap, "Slot {$time} should exist");
            $this->assertFalse($slotMap[$time]['available'], "Slot {$time} should be unavailable");
            $this->assertSame('NO_TECHNICIAN_AVAILABLE', $slotMap[$time]['reason'], "Slot {$time} reason");
        }

        // 10:30 is free (10:30–12:00 does not overlap 09:00–10:30)
        $this->assertArrayHasKey('10:30', $slotMap);
        $this->assertTrue($slotMap['10:30']['available'], 'Slot 10:30 should be available');
        $this->assertNull($slotMap['10:30']['reason']);
    }

    /**
     * TC-06-20 / M3:
     * Given Two tabs attempt to book 10:30 tomorrow; tab 2 books first;
     * when Tab 1 confirms;
     * then Tab 1 receives 409 NO_AVAILABILITY with alternatives suggestions.
     */
    public function test_TC_06_20_M3_race_condition_returns_card_and_suggestions(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh'));

        $world = World::saigonD7();
        $dealership = $world->dealership;
        $advisor = $world->advisor();
        $evBattery = $world->serviceType('EV_BATTERY');

        // Book 09:00 tomorrow (from M1)
        $customer1 = User::factory()->customer()->create(['email' => 'm3_cust1@example.test']);
        ['customer' => $c1, 'vehicle' => $v1] = $world->customerWithVehicle($customer1, [
            'plate_key' => '51M33331',
            'licence_plate' => '51M-333.31',
        ]);
        $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c1->id,
            'vehicleId' => $v1->id,
            'serviceTypeId' => $evBattery->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(201);

        // Tab 2 books 10:30 first
        $customer2 = User::factory()->customer()->create(['email' => 'm3_cust2@example.test']);
        ['customer' => $c2, 'vehicle' => $v2] = $world->customerWithVehicle($customer2, [
            'plate_key' => '51M33332',
            'licence_plate' => '51M-333.32',
        ]);
        $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c2->id,
            'vehicleId' => $v2->id,
            'serviceTypeId' => $evBattery->id,
            'desiredStart' => '2026-11-03T10:30:00+07:00',
        ], ['Idempotency-Key' => (string) Str::uuid()])->assertStatus(201);

        // Tab 1 confirms 10:30
        $customer3 = User::factory()->customer()->create(['email' => 'm3_cust3@example.test']);
        ['customer' => $c3, 'vehicle' => $v3] = $world->customerWithVehicle($customer3, [
            'plate_key' => '51M33333',
            'licence_plate' => '51M-333.33',
        ]);
        $conflictResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c3->id,
            'vehicleId' => $v3->id,
            'serviceTypeId' => $evBattery->id,
            'desiredStart' => '2026-11-03T10:30:00+07:00',
        ], ['Idempotency-Key' => (string) Str::uuid()]);

        $conflictResponse->assertStatus(409);
        $conflictResponse->assertJsonPath('code', 'NO_AVAILABILITY');
        $this->assertNotEmpty($conflictResponse->json('alternatives'));

        // Since 09:00–10:30 and 10:30–12:00 are taken, next available 90-min slot is 12:00
        $firstSuggestion = $conflictResponse->json('alternatives.0.start');
        $this->assertStringContainsString('2026-11-03T12:00:00', $firstSuggestion);
    }

    /**
     * TC-06-20 / M4:
     * Given Full day data for tomorrow (all slots taken for the service);
     * when Customer views availability and calls /availability/next;
     * then Availability returns 0 free slots and next returns next available slot on next open day.
     */
    public function test_TC_06_20_M4_fully_booked_day_and_next_available(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh'));

        $world = World::saigonD7();
        $dealership = $world->dealership;
        $evBattery = $world->serviceType('EV_BATTERY');
        $em = $world->technician('Ngô Bảo Em');
        $bayKev = $world->bay('KEV');

        $customerUser = User::factory()->customer()->create(['email' => 'm4_customer@example.test']);
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle($customerUser, [
            'plate_key' => '51M44444',
            'licence_plate' => '51M-444.44',
        ]);

        // Fill all of tomorrow (2026-11-03 08:00 to 17:00) with a busy booking for Em & KEV
        $allocationRepo = app(ResourceAllocationRepositoryInterface::class);
        $aptId = (string) Str::uuid();
        $startUtc = '2026-11-03 01:00:00+00'; // 08:00 +07
        $endUtc = '2026-11-03 10:00:00+00';   // 17:00 +07
        $nowUtc = '2026-11-02 01:00:00+00';

        Appointment::create([
            'id' => $aptId,
            'reference' => 'APT-M4-BUSY',
            'dealership_id' => $dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $evBattery->id,
            'technician_id' => $em->id,
            'bay_id' => $bayKev->id,
            'status' => 'CONFIRMED',
            'created_by_user_id' => $customerUser->id,
            'start_at' => $startUtc,
            'end_at' => $endUtc,
            'version' => 1,
            'source' => 'STAFF',
            'reminder_opt_in' => true,
        ]);

        $allocationRepo->insertFor($aptId, $dealership->id, [
            ['resource_type' => 'TECHNICIAN', 'resource_id' => $em->id, 'start_at' => $startUtc, 'end_at' => $endUtc],
            ['resource_type' => 'BAY', 'resource_id' => $bayKev->id, 'start_at' => $startUtc, 'end_at' => $endUtc],
        ], CarbonImmutable::now());

        // Customer queries tomorrow's availability
        $availResponse = $this->actingAs($customerUser)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?date=2026-11-03&serviceTypeId={$evBattery->id}"
        );
        $availResponse->assertOk();
        $this->assertSame(0, $availResponse->json('data.freeCount'));

        // Customer calls /availability/next starting after tomorrow 09:00
        $nextResponse = $this->actingAs($customerUser)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$evBattery->id}&after=2026-11-03T09:00:00%2B07:00"
        );
        $nextResponse->assertOk();
        $nextStart = $nextResponse->json('data.nextAvailable.start');
        $this->assertNotNull($nextStart);
        // Next free slot is Wednesday 2026-11-04 08:00
        $this->assertStringContainsString('2026-11-04T08:00:00', $nextStart);
    }
}
