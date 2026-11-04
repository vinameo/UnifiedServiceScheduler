<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class HoldAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * Endpoint 10.1: Advisor sends mode: HOLD -> 422 HOLD_NOT_ALLOWED.
     */
    public function test_endpoint_10_1_advisor_sends_mode_hold_returns_422(): void
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
            $this->assertSame('HOLD_NOT_ALLOWED', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.1: mode: "hold" (lower case) -> 400 VALIDATION_ERROR.
     */
    public function test_endpoint_10_1_mode_lowercase_hold_returns_400(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'hold',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.1: mode: "LOCK" (invalid string) -> 400 VALIDATION_ERROR.
     */
    public function test_endpoint_10_1_mode_invalid_lock_returns_400(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'LOCK',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.1: vehicleId of another customer with HOLD -> 404 VEHICLE_NOT_FOUND.
     */
    public function test_endpoint_10_1_vehicle_of_another_customer_with_hold_returns_404(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA] = $world->customerWithVehicle();
        ['vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($customerUserA)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'vehicleId' => $vehicleB->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(404);
            $this->assertSame('VEHICLE_NOT_FOUND', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.1: Extra fields on hold are ignored.
     */
    public function test_endpoint_10_1_extra_fields_on_hold_are_ignored(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($customerUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-03T10:00:00+07:00',
                    'mode' => 'HOLD',
                    'extra_field' => 'should_be_ignored',
                    'expiresAt' => '2099-01-01T00:00:00Z',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(201);
            $response->assertJsonPath('status', AppointmentStatus::HELD->value);
            // Normal 5-minute expiry, not the 2099 one from payload
            $response->assertJsonPath('expiresAt', '2026-11-02T08:05:00+07:00');
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Missing vehicleId -> 400 VALIDATION_ERROR.
     */
    public function test_endpoint_10_2_missing_vehicle_id_returns_400(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                ['reminderOptIn' => true],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Invalid uuid vehicleId -> 400 VALIDATION_ERROR.
     */
    public function test_endpoint_10_2_invalid_uuid_vehicle_id_returns_400(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => 'not-a-valid-uuid',
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(400);
            $this->assertSame('VALIDATION_ERROR', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: vehicleId of another customer -> 404 VEHICLE_NOT_FOUND.
     */
    public function test_endpoint_10_2_vehicle_of_another_customer_returns_404(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUserA, 'customer' => $customerA] = $world->customerWithVehicle();
        ['vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customerA,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($customerUserA)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicleB->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(404);
            $this->assertSame('VEHICLE_NOT_FOUND', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: {appointment} of another customer -> 404 APPOINTMENT_NOT_FOUND.
     */
    public function test_endpoint_10_2_appointment_of_another_customer_returns_404(): void
    {
        $world = World::saigonD7();
        ['customer' => $customerA] = $world->customerWithVehicle();
        ['user' => $customerUserB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $holdA = AppointmentFactory::hold(
                customer: $customerA,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            // Customer B tries to confirm Customer A's hold
            $response = $this->actingAs($customerUserB)->postJson(
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

            $response->assertStatus(404);
            $this->assertSame('APPOINTMENT_NOT_FOUND', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Confirm a CONFIRMED booking with a new key:
     * - Matching version (v2) -> 422 INVALID_STATUS_TRANSITION
     * - Stale version (v1) -> 412 APPOINTMENT_MODIFIED
     */
    public function test_endpoint_10_2_confirm_a_confirmed_booking_returns_422_or_412(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            // First confirm succeeds -> appointment is now CONFIRMED, version 2
            $confirmResponse = $this->actingAs($customerUser)->postJson(
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
            $confirmResponse->assertStatus(200);
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $confirmResponse->json('status'));
            $this->assertSame(2, $confirmResponse->json('version'));

            // Second confirm with new idempotency key and If-Match "2" (matching version) -> 422 INVALID_STATUS_TRANSITION
            $response422 = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"2"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );
            $response422->assertStatus(422);
            $this->assertSame('INVALID_STATUS_TRANSITION', $response422->json('code'));

            // Third confirm with new idempotency key and If-Match "1" (old version) -> 412 APPOINTMENT_MODIFIED
            $response412 = $this->actingAs($customerUser)->postJson(
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
            $response412->assertStatus(412);
            $this->assertSame('APPOINTMENT_MODIFIED', $response412->json('code'));

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Confirm again with the same key -> the saved 200.
     */
    public function test_endpoint_10_2_confirm_again_with_same_key_returns_saved_200(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $idempotencyKey = (string) Str::uuid();

            // First confirm
            $response1 = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $idempotencyKey,
                ]
            );
            $response1->assertStatus(200);

            // Replay with exact same idempotency key
            $response2 = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $idempotencyKey,
                ]
            );
            $response2->assertStatus(200);
            $this->assertSame($response1->json('id'), $response2->json('id'));
            $this->assertSame($response1->json('version'), $response2->json('version'));
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $response2->json('status'));

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: No If-Match -> 428 PRECONDITION_REQUIRED.
     */
    public function test_endpoint_10_2_missing_if_match_returns_428(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                ],
                [
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(428);
            $this->assertSame('PRECONDITION_REQUIRED', $response->json('code'));
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Staff call confirm -> 403 FORBIDDEN.
     */
    public function test_endpoint_10_2_staff_call_confirm_returns_403(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($advisor)->postJson(
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

            $response->assertStatus(403);
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * Endpoint 10.2: Body has expiresAt far in the future -> Ignored, set to null.
     */
    public function test_endpoint_10_2_body_has_expires_at_is_ignored(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $start = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $start,
                expiresAt: $now->addMinutes(5),
                vehicle: null,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $response = $this->actingAs($customerUser)->postJson(
                "/api/v1/appointments/{$hold->id}/confirm",
                [
                    'vehicleId' => $vehicle->id,
                    'reminderOptIn' => true,
                    'expiresAt' => '2099-01-01T00:00:00Z',
                    'extraField' => 'ignored',
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(200);
            $response->assertJsonPath('status', AppointmentStatus::CONFIRMED->value);
            $response->assertJsonPath('expiresAt', null);

            // Verify in DB that expires_at is null
            $fresh = Appointment::findOrFail($hold->id);
            $this->assertNull($fresh->expires_at);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
