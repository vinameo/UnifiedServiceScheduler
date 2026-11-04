<?php

namespace Tests\Feature\Booking\Cancel;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class CancelAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    private function createAppointment(World $world, CarbonImmutable $now): Appointment
    {
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $apptId = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-'.CarbonImmutable::now()->format('Ymd').'-0088',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now->addDays(7)->setTime(10, 0, 0),
            'end_at' => $now->addDays(7)->setTime(11, 0, 0),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $customerUser->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return Appointment::findOrFail($apptId);
    }

    public function test_TC_07_17_advisor_with_customer_reason_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'SOMETHING_CAME_UP'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_customer_with_advisor_reason_returns_400(): void
    {
        $world = World::saigonD7();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);
        $customerUser = $appointment->customer->user ?? throw new \RuntimeException('Missing customer user');

        $response = $this->actingAs($customerUser)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'DEALERSHIP_ISSUE'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_lowercase_reason_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'customer_busy'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_note_exceeding_500_chars_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            [
                'reason' => 'CUSTOMER_REQUEST',
                'note' => str_repeat('a', 501),
            ],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_note_as_number_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            [
                'reason' => 'CUSTOMER_REQUEST',
                'note' => 12345,
            ],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_non_uuid_appointment_returns_404(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $response = $this->actingAs($advisor)->postJson(
            '/api/v1/appointments/abc/cancel',
            ['reason' => 'CUSTOMER_REQUEST'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(404);
        $this->assertSame('APPOINTMENT_NOT_FOUND', $response->json('code'));
    }

    public function test_TC_07_17_missing_if_match_returns_428(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'CUSTOMER_REQUEST'],
            [
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(428);
        $this->assertSame('PRECONDITION_REQUIRED', $response->json('code'));
    }

    public function test_TC_07_17_weak_if_match_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'CUSTOMER_REQUEST'],
            [
                'If-Match' => 'W/"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $response->json('code'));
    }

    public function test_TC_07_17_missing_idempotency_key_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'CUSTOMER_REQUEST'],
            [
                'If-Match' => '"1"',
            ],
        );

        $response->assertStatus(400);
        $this->assertSame('IDEMPOTENCY_KEY_REQUIRED', $response->json('code'));
    }

    public function test_TC_07_17_same_idempotency_key_different_payload_returns_422(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $sharedKey = (string) Str::uuid();

        $res1 = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'CUSTOMER_BUSY'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => $sharedKey,
            ],
        );
        $res1->assertStatus(200);

        // Replay with same key but different body
        $res2 = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            ['reason' => 'DONE_ELSEWHERE'],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => $sharedKey,
            ],
        );

        $res2->assertStatus(422);
        $this->assertSame('IDEMPOTENCY_KEY_MISMATCH', $res2->json('code'));
    }

    public function test_TC_07_17_extra_body_fields_like_status_confirmed_are_ignored(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::now('UTC');
        $appointment = $this->createAppointment($world, $now);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/cancel",
            [
                'reason' => 'CUSTOMER_REQUEST',
                'status' => 'CONFIRMED',
                'extra_field' => 'should_be_ignored',
            ],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ],
        );

        $response->assertStatus(200);
        $this->assertSame('CANCELLED', $response->json('status'));

        $fresh = Appointment::findOrFail($appointment->id);
        $this->assertSame(AppointmentStatus::CANCELLED, $fresh->status);
    }
}
