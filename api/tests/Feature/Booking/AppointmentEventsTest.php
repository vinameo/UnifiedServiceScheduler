<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AppointmentEventsTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_INV_04_11_one_event_per_write(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');

        // Step 1: Create appointment
        $createResponse = $this->actingAs($user)
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

        $eventsAfterCreate = DB::table('appointment_events')
            ->where('appointment_id', $apptId)
            ->orderBy('version')
            ->get();

        $this->assertCount(1, $eventsAfterCreate);
        $firstEvent = $eventsAfterCreate->first();
        $this->assertNotNull($firstEvent);
        $this->assertSame(1, $firstEvent->version);
        $this->assertSame('CREATED', $firstEvent->type);
        $this->assertNull($firstEvent->from_status);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $firstEvent->to_status);

        // Step 2: Touch appointment (reminder_opt_in toggled)
        $touchResponse = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $touchResponse->assertStatus(200)
            ->assertHeader('ETag', '"2"');

        $eventsAfterTouch = DB::table('appointment_events')
            ->where('appointment_id', $apptId)
            ->orderBy('version')
            ->get();

        $this->assertCount(2, $eventsAfterTouch);
        $secondEvent = $eventsAfterTouch->get(1);
        $this->assertNotNull($secondEvent);
        $this->assertSame(2, $secondEvent->version);
        $this->assertSame('CONFIRMED', $secondEvent->type);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $secondEvent->from_status);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $secondEvent->to_status);

        // Step 3: Transition appointment (CONFIRMED -> CHECKED_IN)
        $transitionResponse = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/transition", [
                'to' => AppointmentStatus::CHECKED_IN->value,
            ], [
                'Accept' => 'application/json',
                'If-Match' => '"2"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $transitionResponse->assertStatus(200)
            ->assertHeader('ETag', '"3"');

        $eventsAfterTransition = DB::table('appointment_events')
            ->where('appointment_id', $apptId)
            ->orderBy('version')
            ->get();

        $this->assertCount(3, $eventsAfterTransition);
        $thirdEvent = $eventsAfterTransition->get(2);
        $this->assertNotNull($thirdEvent);
        $this->assertSame(3, $thirdEvent->version);
        $this->assertSame('CHECKED_IN', $thirdEvent->type);
        $this->assertSame(AppointmentStatus::CONFIRMED->value, $thirdEvent->from_status);
        $this->assertSame(AppointmentStatus::CHECKED_IN->value, $thirdEvent->to_status);

        // Assert appointment table version matches the latest event version
        /** @var Appointment $appointment */
        $appointment = Appointment::query()->findOrFail($apptId);
        $this->assertSame(3, $appointment->version);
    }
}
