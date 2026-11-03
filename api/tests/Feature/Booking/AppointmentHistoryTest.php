<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Services\AppointmentEventRecorderInterface;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AppointmentHistoryTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_D_05_4_history_is_staff_only(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $advisor = $world->advisor();

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');

        // Step 1: Create appointment via test route
        $createResponse = $this->actingAs($customerUser)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $createResponse->assertStatus(201)
            ->assertHeader('ETag', '"1"');

        $apptId = $createResponse->json('id');
        $this->assertNotNull($apptId);

        // Step 2: Touch appointment (reminder_opt_in toggled)
        $touchResponse = $this->actingAs($customerUser)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $touchResponse->assertStatus(200)
            ->assertHeader('ETag', '"2"');

        // Step 3: Customer GET /history -> 403 FORBIDDEN
        $customerHistoryResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/appointments/{$apptId}/history");

        $customerHistoryResponse->assertStatus(403);

        // Step 4: Advisor GET /history -> 200 OK with 2 rows in time order
        $advisorHistoryResponse = $this->actingAs($advisor)
            ->getJson("/api/v1/appointments/{$apptId}/history");

        $advisorHistoryResponse->assertStatus(200)
            ->assertJsonCount(2);

        $events = $advisorHistoryResponse->json();

        // Event 1: CREATED
        $this->assertSame('CREATED', $events[0]['type']);
        $this->assertNull($events[0]['fromStatus']);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $events[0]['toStatus']);
        $this->assertSame(1, $events[0]['version']);
        $this->assertNotNull($events[0]['occurredAt']);
        $this->assertNotNull($events[0]['actor']);

        // Event 2: CONFIRMED (touched)
        $this->assertSame('CONFIRMED', $events[1]['type']);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $events[1]['fromStatus']);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $events[1]['toStatus']);
        $this->assertSame(2, $events[1]['version']);
        $this->assertNotNull($events[1]['occurredAt']);

        // Assert chronological order (occurredAt ascending)
        $this->assertLessThanOrEqual($events[1]['occurredAt'], $events[0]['occurredAt']);

        // Assert no phone/email personal data leaks anywhere in the response
        $rawJson = (string) $advisorHistoryResponse->getContent();
        $this->assertStringNotContainsString($customer->phone, $rawJson);
        $this->assertStringNotContainsString($customerUser->email, $rawJson);
        $this->assertStringNotContainsString('phone', strtolower($rawJson));
        $this->assertStringNotContainsString('email', strtolower($rawJson));
    }

    public function test_history_d2_staff_and_unknown_id_return_404(): void
    {
        $world1 = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world1->customerWithVehicle();
        $service = $world1->serviceType('OIL_CHANGE');
        $bay = $world1->bay('K1');
        $tech = $world1->technician('Trần Minh An');

        $appointmentD1 = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0099',
            'dealership_id' => $world1->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUser->id,
        ]);

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        // D2 advisor calling D1's appointment history -> 404 APPOINTMENT_NOT_FOUND
        $response = $this->actingAs($advisorD2)->getJson("/api/v1/appointments/{$appointmentD1->id}/history");
        $response->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // Unknown UUID -> 404 APPOINTMENT_NOT_FOUND
        $unknownId = (string) Str::uuid();
        $responseUnknown = $this->actingAs($advisorD2)->getJson("/api/v1/appointments/{$unknownId}/history");
        $responseUnknown->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');
    }

    public function test_history_system_event_returns_null_actor(): void
    {
        $world = World::saigonD7();
        ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $advisor = $world->advisor();

        $appointment = Appointment::create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261102-0098',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $world->technician('Trần Minh An')->id,
            'bay_id' => $world->bay('K1')->id,
            'status' => AppointmentStatus::EXPIRED,
            'start_at' => CarbonImmutable::parse('2026-11-02 09:00:00+00'),
            'end_at' => CarbonImmutable::parse('2026-11-02 10:00:00+00'),
            'version' => 2,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $customerUser->id,
        ]);

        /** @var AppointmentEventRecorderInterface $recorder */
        $recorder = app(AppointmentEventRecorderInterface::class);
        $recorder->record(
            appointmentId: $appointment->id,
            dealershipId: $world->dealership->id,
            type: AppointmentEventType::EXPIRED,
            version: 2,
            occurredAt: CarbonImmutable::now(),
            fromStatus: AppointmentStatus::HELD,
            toStatus: AppointmentStatus::EXPIRED,
            actorUserId: null,
            actorRole: null,
            data: [],
        );

        $response = $this->actingAs($advisor)->getJson("/api/v1/appointments/{$appointment->id}/history");
        $response->assertStatus(200);

        $events = $response->json();
        $this->assertCount(1, $events);
        $this->assertNull($events[0]['actor']);
        $this->assertSame('EXPIRED', $events[0]['type']);
    }
}
