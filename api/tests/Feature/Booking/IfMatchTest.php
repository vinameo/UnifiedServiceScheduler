<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AppointmentModifiedDetected;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class IfMatchTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_04_7_missing_if_match_is_428(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-0001',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');

        $fresh = Appointment::findOrFail($apptId);
        $this->assertSame(1, $fresh->version);
    }

    public function test_AC_04_22_412_carries_latest_data(): void
    {
        Event::fake([AppointmentModifiedDetected::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-0002',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 4,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $response = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'If-Match' => '"3"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(412)
            ->assertHeader('ETag', '"4"')
            ->assertJsonPath('code', 'APPOINTMENT_MODIFIED')
            ->assertJsonPath('appointment.version', 4)
            ->assertJsonPath('appointment.id', $apptId);

        Event::assertDispatched(function (AppointmentModifiedDetected $event) use ($apptId) {
            return $event->appointmentId === $apptId && $event->expectedVersion === 3;
        });
    }
}
