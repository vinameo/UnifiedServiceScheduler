<?php

namespace Tests\Feature\Booking\Cancel;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\CancelReason;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class CancelAppointmentTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_07_1_cancel_frees_the_slot_at_once(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);

        $advisor = $world->advisor();
        ['customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        ['customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle(
            null,
            ['make' => 'Honda', 'model' => 'City', 'plate_key' => '51K67890', 'licence_plate' => '51K-678.90']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';

            // 1. Book appointment A
            $bookAResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerA->id,
                'vehicleId' => $vehicleA->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $bookAResponse->assertStatus(201);
            $appointmentAId = $bookAResponse->json('id');
            $this->assertEquals(1, $bookAResponse->json('version'));

            // Verify active allocations exist for appointment A
            $activeAllocationsBefore = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentAId)
                ->where('active', true)
                ->count();
            $this->assertGreaterThan(0, $activeAllocationsBefore);

            // 2. Advisor cancels appointment A
            $cancelNote = 'Customer will call back to book next week.';
            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentAId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
                'note' => $cancelNote,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $cancelResponse->assertStatus(200);
            $cancelResponse->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancelResponse->json('status'));
            $this->assertSame(2, $cancelResponse->json('version'));

            // Check database columns (INV-07.6)
            /** @var Appointment $appointmentA */
            $appointmentA = Appointment::findOrFail($appointmentAId);
            $this->assertSame(AppointmentStatus::CANCELLED, $appointmentA->status);
            $this->assertSame(CancelReason::CUSTOMER_BUSY->value, $appointmentA->cancel_reason);
            $this->assertSame($cancelNote, $appointmentA->cancel_note);
            $this->assertNotNull($appointmentA->cancelled_at);
            $this->assertSame($advisor->id, $appointmentA->cancelled_by_user_id);
            $this->assertSame(2, $appointmentA->version);

            // 0 active allocations of A (INV-07.1)
            $activeAllocationsAfter = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentAId)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocationsAfter);

            // 3. Book appointment B at the exact same slot (AC-07.1)
            $bookBResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerB->id,
                'vehicleId' => $vehicleB->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $bookBResponse->assertStatus(201);
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_5_advisor_has_no_time_limit(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        // Given clock is 08:00 and booking starts at 08:30 (30 minutes away)
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $startAt = '2026-11-02T08:30:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $startAt,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Advisor cancels with only 30 minutes left
            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_REQUEST->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $cancelResponse->assertStatus(200);
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancelResponse->json('status'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
