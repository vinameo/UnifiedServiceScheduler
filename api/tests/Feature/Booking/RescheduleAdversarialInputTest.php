<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class RescheduleAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    private function createAppointment(
        World $world,
        string $start = '2026-11-03T09:00:00+07:00',
        ?User $user = null,
        ?Customer $customer = null,
        ?Vehicle $vehicle = null,
    ): Appointment {
        $advisor = $world->advisor();
        if ($customer === null || $vehicle === null) {
            ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        }
        $serviceType = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $serviceType->id,
            'desiredStart' => $start,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $response->assertStatus(201);

        /** @var Appointment $appointment */
        $appointment = Appointment::findOrFail($response->json('id'));

        return $appointment;
    }

    /**
     * Row 1: `newStart` without offset -> 400.
     */
    public function test_row_1_new_start_without_offset_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T14:00:00'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 2: `newStart` = 2026-11-02T14:05:00+07:00 -> 400 (not on 15-minute step).
     */
    public function test_row_2_new_start_not_on_15_minute_step_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-02T14:05:00+07:00'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 3: `newStart` equal to current start (in different offset, same instant) -> 422 RESCHEDULE_SAME_TIME.
     */
    public function test_row_3_new_start_equal_to_current_start_in_different_offset_returns_422(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Appointment starts at 2026-11-03T09:00:00+07:00, which is 2026-11-03T02:00:00Z
            $appointment = $this->createAppointment($world, '2026-11-03T09:00:00+07:00');

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T02:00:00Z'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(422);
            $this->assertSame('RESCHEDULE_SAME_TIME', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 4: `newStart` in past -> 422 START_IN_PAST.
     */
    public function test_row_4_new_start_in_past_returns_422_start_in_past(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-01T10:00:00+07:00'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(422);
            $this->assertSame('START_IN_PAST', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 5: `newStart` on closed day -> 422 OUTSIDE_OPENING_HOURS.
     */
    public function test_row_5_new_start_on_closed_day_returns_422_outside_opening_hours(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            // Sunday 2026-11-08 is closed
            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-08T10:00:00+07:00'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(422);
            $this->assertSame('OUTSIDE_OPENING_HOURS', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 6: `keepTechnician` = "yes" -> 400.
     */
    public function test_row_6_keep_technician_string_yes_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                [
                    'newStart' => '2026-11-03T14:00:00+07:00',
                    'keepTechnician' => 'yes',
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 7: Body has extra field `serviceTypeId` -> ignored, returns 200, serviceType unchanged.
     */
    public function test_row_7_body_has_extra_field_service_type_id_ignored(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);
            $originalServiceTypeId = $appointment->service_type_id;

            $otherService = $world->serviceType('MAINT_20K');
            $this->assertNotSame($originalServiceTypeId, $otherService->id);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                [
                    'newStart' => '2026-11-03T14:00:00+07:00',
                    'keepTechnician' => true,
                    'serviceTypeId' => $otherService->id,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(200);
            $this->assertSame($originalServiceTypeId, $response->json('serviceType.id'));

            /** @var Appointment $fresh */
            $fresh = Appointment::findOrFail($appointment->id);
            $this->assertSame($originalServiceTypeId, $fresh->service_type_id);
            $this->assertSame(2, $fresh->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $fresh->status);
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 8: `{appointment}` of another customer when authenticated as customer -> 404 APPOINTMENT_NOT_FOUND.
     */
    public function test_row_8_appointment_of_another_customer_returns_404(): void
    {
        $world = World::saigonD7();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Customer A
            ['user' => $userA] = $world->customerWithVehicle();

            // Customer B with appointment
            ['user' => $userB, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
            $appointmentB = $this->createAppointment($world, '2026-11-03T09:00:00+07:00', $userB, $customerB, $vehicleB);

            $response = $this->actingAs($userA)->postJson(
                "/api/v1/appointments/{$appointmentB->id}/reschedule",
                ['newStart' => '2026-11-03T14:00:00+07:00'],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(404);
            $this->assertSame('APPOINTMENT_NOT_FOUND', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 9: Missing `If-Match` -> 428 PRECONDITION_REQUIRED.
     */
    public function test_row_9_missing_if_match_returns_428(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T14:00:00+07:00'],
                [
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
            );

            $response->assertStatus(428);
            $this->assertSame('PRECONDITION_REQUIRED', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 10: Missing `Idempotency-Key` -> 400 IDEMPOTENCY_KEY_REQUIRED.
     */
    public function test_row_10_missing_idempotency_key_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T14:00:00+07:00'],
                [
                    'If-Match' => '"1"',
                ],
            );

            $response->assertStatus(400);
            $this->assertSame('IDEMPOTENCY_KEY_REQUIRED', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 11: Same key, different `newStart` -> 422 IDEMPOTENCY_KEY_MISMATCH.
     */
    public function test_row_11_same_key_different_new_start_returns_422(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);
            $sharedKey = (string) Str::uuid();

            // First request succeeds
            $res1 = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T11:00:00+07:00', 'keepTechnician' => true],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $sharedKey,
                ],
            );
            $res1->assertStatus(200);

            // Replay with same key but different newStart
            $res2 = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T14:00:00+07:00', 'keepTechnician' => true],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $sharedKey,
                ],
            );

            $res2->assertStatus(422);
            $this->assertSame('IDEMPOTENCY_KEY_MISMATCH', $res2->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Row 12: Same key resent after success (when booking has newer version) -> returns saved 200, not 412.
     */
    public function test_row_12_same_key_resent_after_success_with_newer_version_returns_saved_200(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = $this->createAppointment($world);
            $sharedKey = (string) Str::uuid();

            // First request succeeds and brings appointment to version 2
            $res1 = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T11:00:00+07:00', 'keepTechnician' => true],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $sharedKey,
                ],
            );
            $res1->assertStatus(200);
            $this->assertSame(2, $res1->json('version'));

            // Simulate booking advancing to a newer version (e.g. version 3)
            DB::table('appointments')->where('id', $appointment->id)->update(['version' => 3]);

            /** @var Appointment $fresh */
            $fresh = Appointment::findOrFail($appointment->id);
            $this->assertSame(3, $fresh->version);

            // Resend with original key and original If-Match "1"
            $res2 = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/reschedule",
                ['newStart' => '2026-11-03T11:00:00+07:00', 'keepTechnician' => true],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $sharedKey,
                ],
            );

            $res2->assertStatus(200);
            $res2->assertHeader('Idempotent-Replayed', 'true');
            $this->assertSame(2, $res2->json('version'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
