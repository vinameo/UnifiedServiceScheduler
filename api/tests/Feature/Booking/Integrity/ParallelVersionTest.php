<?php

namespace Tests\Feature\Booking\Integrity;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelVersionTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_04_6_two_changes_on_same_version_one_wins(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-23 09:00:00', 'UTC');
        $apptId = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-20261123-0001',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now,
            'end_at' => $now->addHour(),
            'version' => 3,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $results = Parallel::run(2, function (int $i) use ($user, $apptId) {
            return [
                'method' => 'POST',
                'uri' => "/api/v1/_testing/appointments/{$apptId}/touch",
                'user' => $user,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'If-Match' => '"3"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [],
            ];
        }, delaySeconds: 0.35);

        $this->assertCount(2, $results);

        $statuses = [$results[0]['status'], $results[1]['status']];
        sort($statuses);

        // One 200, one 412
        $this->assertSame([200, 412], $statuses, 'Expected exactly one 200 and one 412');

        // Check ETag header on the winning 200 and losing 412
        foreach ($results as $res) {
            $etag = $res['headers']['etag'][0] ?? $res['headers']['ETag'][0] ?? null;
            $this->assertSame('"4"', $etag);
        }

        // Final version in database is exactly 4
        $fresh = Appointment::findOrFail($apptId);
        $this->assertSame(4, $fresh->version);
    }
}
