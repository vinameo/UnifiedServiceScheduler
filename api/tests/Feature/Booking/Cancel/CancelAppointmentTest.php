<?php

namespace Tests\Feature\Booking\Cancel;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\CancelReason;
use App\Models\Appointment;
use App\Models\Bay;
use App\Models\Technician;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\AppointmentFactory;
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

    public function test_AC_07_2_cancelling_twice_returns_current_state(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Cancel first time -> v2
            $cancel1 = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $cancel1->assertStatus(200);
            $cancel1->assertHeader('ETag', '"2"');
            $this->assertSame(2, $cancel1->json('version'));

            $eventsCountBefore = DB::table('appointment_events')
                ->where('appointment_id', $appointmentId)
                ->count();

            // Cancel again with If-Match "2"
            $cancel2 = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"2"',
            ]);
            $cancel2->assertStatus(200);
            $cancel2->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancel2->json('status'));
            $this->assertSame(2, $cancel2->json('version'));

            // Cancel again with old If-Match "1" (D-07.4)
            $cancel3 = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $cancel3->assertStatus(200);
            $cancel3->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancel3->json('status'));
            $this->assertSame(2, $cancel3->json('version'));

            // No new history rows written (D-07.4)
            $eventsCountAfter = DB::table('appointment_events')
                ->where('appointment_id', $appointmentId)
                ->count();
            $this->assertSame($eventsCountBefore, $eventsCountAfter);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_3_completed_cannot_be_cancelled(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Move appointment directly to COMPLETED in database
            DB::table('appointments')
                ->where('id', $appointmentId)
                ->update([
                    'status' => AppointmentStatus::COMPLETED->value,
                    'version' => 2,
                ]);

            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"2"',
            ]);

            $cancelResponse->assertStatus(422);
            $cancelResponse->assertJson([
                'code' => 'INVALID_STATUS_TRANSITION',
                'currentStatus' => 'COMPLETED',
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_6_staff_reason_required(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'note' => 'Some note',
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $cancelResponse->assertStatus(400);
            $cancelResponse->assertJsonValidationErrors(['reason']);
            $cancelResponse->assertJson(['code' => 'VALIDATION_ERROR']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_8_foreman_and_admin_cannot_cancel(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $foreman = $world->foreman();
        $admin = $world->admin();

        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Foreman gets 403
            $foremanResponse = $this->actingAs($foreman)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $foremanResponse->assertStatus(403);

            // Admin gets 403
            $adminResponse = $this->actingAs($admin)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $adminResponse->assertStatus(403);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_11_stale_and_missing_if_match(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Bump version to 2
            DB::table('appointments')->where('id', $appointmentId)->update(['version' => 2]);

            // Stale If-Match "1"
            $staleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $staleResponse->assertStatus(412);
            $staleResponse->assertHeader('ETag', '"2"');
            $staleResponse->assertJson([
                'code' => 'APPOINTMENT_MODIFIED',
                'appointment' => [
                    'version' => 2,
                ],
            ]);

            // Missing If-Match -> 428
            $missingResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $missingResponse->assertStatus(428);
            $missingResponse->assertJson(['code' => 'PRECONDITION_REQUIRED']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    #[DataProvider('nonCancellableStatuses')]
    public function test_INV_07_2_non_cancellable_statuses(AppointmentStatus $status): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            DB::table('appointments')
                ->where('id', $appointmentId)
                ->update([
                    'status' => $status->value,
                    'version' => 2,
                ]);

            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"2"',
            ]);

            $cancelResponse->assertStatus(422);
            $cancelResponse->assertJson([
                'code' => 'INVALID_STATUS_TRANSITION',
                'currentStatus' => $status->value,
            ]);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * @return array<string, array{AppointmentStatus}>
     */
    public static function nonCancellableStatuses(): array
    {
        return [
            'CHECKED_IN' => [AppointmentStatus::CHECKED_IN],
            'IN_PROGRESS' => [AppointmentStatus::IN_PROGRESS],
            'NO_SHOW' => [AppointmentStatus::NO_SHOW],
            'EXPIRED' => [AppointmentStatus::EXPIRED],
        ];
    }

    public function test_retry_with_same_key_replays(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            $key = (string) Str::uuid();

            // Cancel first time
            $cancel1 = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => $key,
                'If-Match' => '"1"',
            ]);

            $cancel1->assertStatus(200);
            $cancel1->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancel1->json('status'));
            $this->assertSame(2, $cancel1->json('version'));

            // Resend with same key K
            $cancel2 = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
            ], [
                'Idempotency-Key' => $key,
                'If-Match' => '"1"',
            ]);

            $cancel2->assertStatus(200);
            $cancel2->assertHeader('Idempotent-Replayed', 'true');
            $cancel2->assertHeader('ETag', '"2"');
            $this->assertSame(AppointmentStatus::CANCELLED->value, $cancel2->json('status'));
            $this->assertSame(2, $cancel2->json('version'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_4_customer_too_late(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        // Clock 08:00, booking at 09:00 (1 hour left)
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $startAt = '2026-11-02T09:00:00+07:00';
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

            // Customer attempts to cancel in the app
            $customerUser = $customer->user;
            $cancelResponse = $this->actingAs($customerUser)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $cancelResponse->assertStatus(422);
            $cancelResponse->assertJson([
                'code' => 'TOO_LATE_TO_CANCEL',
                'dealershipPhone' => '028 3812 3456',
            ]);

            // Still CONFIRMED
            /** @var Appointment $appointment */
            $appointment = Appointment::findOrFail($appointmentId);
            $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->status);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_7_cannot_cancel_another_customers_booking(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customerA] = $world->customerWithVehicle();
        ['customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            // Booking belongs to Customer B
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerB->id,
                'vehicleId' => $vehicleB->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Customer A attempts to cancel Customer B's booking
            $cancelResponse = $this->actingAs($customerA->user)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);

            $cancelResponse->assertStatus(404);
            $cancelResponse->assertJson(['code' => 'APPOINTMENT_NOT_FOUND']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_07_10_outbox_only_for_confirmed(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');
        $technician = $world->technician('An');
        $bay = $world->bay('K2');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $day1 = CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh');
            $day2 = CarbonImmutable::parse('2026-11-04 09:00:00', 'Asia/Ho_Chi_Minh');

            // 1. Confirmed booking
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $day1->toIso8601String(),
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $confirmedId = $createResponse->json('id');

            // 2. Held booking (using AppointmentFactory)
            $heldAppointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $technician,
                bay: $bay,
                start: $day2,
                end: $day2->addHour(),
                serviceType: $serviceType,
                customer: $customer,
                vehicle: $vehicle,
                status: AppointmentStatus::HELD,
            );
            $heldId = $heldAppointment->id;

            // Customer cancels confirmed booking
            $cancelConfirmed = $this->actingAs($customer->user)->postJson("/api/v1/appointments/{$confirmedId}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $cancelConfirmed->assertStatus(200);

            // Customer cancels held booking
            $cancelHeld = $this->actingAs($customer->user)->postJson("/api/v1/appointments/{$heldId}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $cancelHeld->assertStatus(200);

            // Check outbox: CONFIRMED produced 1 event_id (3 channel rows), HELD produced 0
            $confirmedOutbox = DB::table('outbox_messages')
                ->where('appointment_id', $confirmedId)
                ->where('event_type', 'AppointmentCancelled')
                ->get();
            $this->assertCount(3, $confirmedOutbox);
            $distinctEventIds = $confirmedOutbox->pluck('event_id')->unique();
            $this->assertCount(1, $distinctEventIds);

            $heldOutbox = DB::table('outbox_messages')
                ->where('appointment_id', $heldId)
                ->count();
            $this->assertSame(0, $heldOutbox);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_INV_07_3_two_hour_boundary(): void
    {
        $world = World::saigonD7();
        ['customer' => $customer, 'vehicle' => $vehicle1] = $world->customerWithVehicle();
        $vehicle2 = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $vehicle3 = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $serviceType = $world->serviceType('OIL_CHANGE');

        $technicians = Technician::where('dealership_id', $world->dealership->id)->get();
        $bays = Bay::where('dealership_id', $world->dealership->id)->get();

        $tech1 = $technicians->get(0);
        assert($tech1 instanceof Technician);
        $tech2 = $technicians->get(1);
        assert($tech2 instanceof Technician);
        $tech3 = $technicians->get(2);
        assert($tech3 instanceof Technician);

        $bay1 = $bays->get(0);
        assert($bay1 instanceof Bay);
        $bay2 = $bays->get(1);
        assert($bay2 instanceof Bay);
        $bay3 = $bays->get(2);
        assert($bay3 instanceof Bay);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 1. Exactly 2 hours: 10:00:00
            $startExact = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $exactApt = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech1,
                bay: $bay1,
                start: $startExact,
                end: $startExact->addHour(),
                serviceType: $serviceType,
                customer: $customer,
                vehicle: $vehicle1,
                status: AppointmentStatus::CONFIRMED,
            );

            // 2. Less than 2 hours: 09:59:00
            $startClose = CarbonImmutable::parse('2026-11-02 09:59:00', 'Asia/Ho_Chi_Minh');
            $closeApt = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech2,
                bay: $bay2,
                start: $startClose,
                end: $startClose->addHour(),
                serviceType: $serviceType,
                customer: $customer,
                vehicle: $vehicle2,
                status: AppointmentStatus::CONFIRMED,
            );

            // 3. Held at 08:30 (30 minutes left, but HELD has no 2-hour limit)
            $startHeld = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');
            $heldApt = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech3,
                bay: $bay3,
                start: $startHeld,
                end: $startHeld->addHour(),
                serviceType: $serviceType,
                customer: $customer,
                vehicle: $vehicle3,
                status: AppointmentStatus::HELD,
            );

            // Cancel exact 2 hours -> 200
            $resExact = $this->actingAs($customer->user)->postJson("/api/v1/appointments/{$exactApt->id}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $resExact->assertStatus(200);

            // Cancel 09:59 -> 422
            $resClose = $this->actingAs($customer->user)->postJson("/api/v1/appointments/{$closeApt->id}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $resClose->assertStatus(422);
            $resClose->assertJson(['code' => 'TOO_LATE_TO_CANCEL']);

            // Cancel HELD at 08:30 -> 200
            $resHeld = $this->actingAs($customer->user)->postJson("/api/v1/appointments/{$heldApt->id}/cancel", [], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $resHeld->assertStatus(200);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_customer_does_not_see_staff_note(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $tomorrow = '2026-11-03T09:00:00+07:00';
            $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $tomorrow,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $createResponse->assertStatus(201);
            $appointmentId = $createResponse->json('id');

            // Advisor cancels with note
            $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/cancel", [
                'reason' => CancelReason::CUSTOMER_BUSY->value,
                'note' => 'Internal sensitive note: customer was dissatisfied with pricing.',
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
                'If-Match' => '"1"',
            ]);
            $cancelResponse->assertStatus(200);

            // Customer views the appointment (GET /appointments/{id})
            $customerView = $this->actingAs($customer->user)->getJson("/api/v1/appointments/{$appointmentId}");
            $customerView->assertStatus(200);
            $customerView->assertJsonPath('cancellation.byRole', 'SERVICE_ADVISOR');
            $customerView->assertJsonPath('cancellation.reason', null);
            $customerView->assertJsonPath('cancellation.note', null);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
