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

class MiddlewareOrderTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_INV_04_8_idempotency_runs_before_if_match(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        // 1. Create appointment at version 1
        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261122-0055',
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

        $key = (string) Str::uuid();

        // 2. Perform a touch change with key K and If-Match: "1" -> 200, appointment becomes v2
        $response1 = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'If-Match' => '"1"',
                'Idempotency-Key' => $key,
            ]);

        $response1->assertStatus(200)
            ->assertHeader('ETag', '"2"');

        $fresh = Appointment::findOrFail($apptId);
        $this->assertSame(2, $fresh->version);

        // 3. Send key K again with OLD If-Match: "1"
        // Because idempotency runs before if-match, it must return the saved 200, NOT 412!
        $response2 = $this->actingAs($user)
            ->postJson("/api/v1/_testing/appointments/{$apptId}/touch", [], [
                'Accept' => 'application/json',
                'If-Match' => '"1"',
                'Idempotency-Key' => $key,
            ]);

        $response2->assertStatus(200)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('version', 2);
    }
}
