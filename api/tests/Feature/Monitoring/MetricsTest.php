<?php

namespace Tests\Feature\Monitoring;

use App\Domain\Monitoring\Repositories\MetricCounterRepositoryInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Monolog\Level;
use RuntimeException;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Logging\CapturesLogs;
use Tests\Support\World;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use AssertsNoOverlap;
    use CapturesLogs;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCapturesLogs();
    }

    /**
     * TC-08-02 (AC-08.2; Integration):
     * given No free slot;
     * when POST /appointments → 409;
     * then booking_requests_total{outcome="no_availability"} +1; other outcomes unchanged.
     */
    public function test_AC_08_2_no_availability_is_counted(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Corolla', 'plate_key' => '51H99999', 'licence_plate' => '51H-999.99']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        // Disable all bays so CandidateFinder finds zero availability -> 409 NO_AVAILABILITY
        DB::table('bays')->where('dealership_id', $world->dealership->id)->update(['active' => false]);

        $repo = app(MetricCounterRepositoryInterface::class);
        $beforeNoAvail = $repo->get('booking_requests_total', 'outcome="no_availability"');
        $beforeConfirmed = $repo->get('booking_requests_total', 'outcome="confirmed"');
        $beforeValidationError = $repo->get('booking_requests_total', 'outcome="validation_error"');
        $beforeError = $repo->get('booking_requests_total', 'outcome="error"');

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        $this->assertSame($beforeNoAvail + 1, $repo->get('booking_requests_total', 'outcome="no_availability"'));
        $this->assertSame($beforeConfirmed, $repo->get('booking_requests_total', 'outcome="confirmed"'));
        $this->assertSame($beforeValidationError, $repo->get('booking_requests_total', 'outcome="validation_error"'));
        $this->assertSame($beforeError, $repo->get('booking_requests_total', 'outcome="error"'));
    }

    /**
     * TC-08-06 (AC-08.6, INV-08.3; Integration):
     * given Read counters before;
     * when 201; 422; 500 (test route); a replay with the same key; 412 (F04 touch); failed sign-in; one AllocationRetried;
     * then confirmed, validation_error, error, idempotent_replays_total, appointment_modified_total,
     *      sign_in_failures_total, allocation_retries_total each +1;
     *      the replay does not increase booking_requests_total.
     */
    public function test_AC_08_6_each_counter_moves_once(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Camry', 'plate_key' => '51H77777', 'licence_plate' => '51H-777.77']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        $repo = app(MetricCounterRepositoryInterface::class);

        // Read all counters before
        $beforeConfirmed = $repo->get('booking_requests_total', 'outcome="confirmed"');
        $beforeValidationError = $repo->get('booking_requests_total', 'outcome="validation_error"');
        $beforeError = $repo->get('booking_requests_total', 'outcome="error"');
        $beforeReplays = $repo->get('idempotent_replays_total');
        $beforeModified = $repo->get('appointment_modified_total');
        $beforeSignInFailures = $repo->get('sign_in_failures_total');
        $beforeAllocationRetries = $repo->get('allocation_retries_total');

        $idempotencyKey = (string) Str::uuid();
        $bookingPayload = [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ];

        // 1. 201 (confirmed booking)
        $res201 = $this->actingAs($advisor)->postJson('/api/v1/appointments', $bookingPayload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $res201->assertStatus(201);
        $appointmentId = (string) $res201->json('id');

        // 2. 422 (validation error on booking, e.g. start in past)
        $res422 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-01T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $res422->assertStatus(422);

        // 3. 500 (deliberate 500 test route)
        $res500 = $this->getJson('/api/v1/_testing/fail');
        $res500->assertStatus(500);

        // 4. Replay with the same key (idempotent replay)
        $resReplay = $this->actingAs($advisor)->postJson('/api/v1/appointments', $bookingPayload, [
            'Idempotency-Key' => $idempotencyKey,
        ]);
        $resReplay->assertStatus(201);
        $this->assertSame('true', $resReplay->headers->get('Idempotent-Replayed'));

        // 5. 412 (F04 touch with mismatched version)
        $res412 = $this->actingAs($advisor)->postJson("/api/v1/_testing/appointments/{$appointmentId}/touch", [], [
            'If-Match' => '"9999"',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $res412->assertStatus(412)
            ->assertJsonPath('code', 'APPOINTMENT_MODIFIED');

        // 6. Failed sign-in
        $resSignIn = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'nonexistent_customer@example.com',
            'password' => 'WrongPassword123!',
        ]);
        $resSignIn->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');

        // 7. One AllocationRetried
        $resRetry = $this->postJson('/api/v1/_testing/allocation-retry');
        $resRetry->assertStatus(200);

        // Assert all 7 counters moved by exactly +1
        $this->assertSame($beforeConfirmed + 1, $repo->get('booking_requests_total', 'outcome="confirmed"'));
        $this->assertSame($beforeValidationError + 1, $repo->get('booking_requests_total', 'outcome="validation_error"'));
        $this->assertSame($beforeError + 1, $repo->get('booking_requests_total', 'outcome="error"'));
        $this->assertSame($beforeReplays + 1, $repo->get('idempotent_replays_total'));
        $this->assertSame($beforeModified + 1, $repo->get('appointment_modified_total'));
        $this->assertSame($beforeSignInFailures + 1, $repo->get('sign_in_failures_total'));
        $this->assertSame($beforeAllocationRetries + 1, $repo->get('allocation_retries_total'));

        // Assert that the replay did NOT increase booking_requests_total
        $totalBookingsBefore = $beforeConfirmed + $beforeValidationError + $beforeError;
        $totalBookingsAfter = $repo->get('booking_requests_total', 'outcome="confirmed"')
            + $repo->get('booking_requests_total', 'outcome="validation_error"')
            + $repo->get('booking_requests_total', 'outcome="error"');
        $this->assertSame($totalBookingsBefore + 3, $totalBookingsAfter);
    }

    /**
     * TC-08-15 (INV-08.4; Integration):
     * given A fake MetricCounterRepository that throws;
     * when POST /appointments;
     * then 201 as usual; 1 warning log line.
     */
    public function test_INV_08_4_metrics_failure_does_not_break_request(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Fortuner', 'plate_key' => '51H66666', 'licence_plate' => '51H-666.66']
        );
        $oilChange = $world->serviceType('OIL_CHANGE');

        // Bind throwing MetricCounterRepository
        $throwingRepo = new class implements MetricCounterRepositoryInterface
        {
            public function increment(string $name, string $labels = '', int $by = 1): void
            {
                throw new RuntimeException('Simulated metric_counters database failure');
            }

            public function incrementDuration(float $durationSeconds): void
            {
                throw new RuntimeException('Simulated histogram update failure');
            }

            public function get(string $name, string $labels = ''): int
            {
                return 0;
            }

            public function all(): Collection
            {
                return collect();
            }
        };

        $this->app->instance(MetricCounterRepositoryInterface::class, $throwingRepo);

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilChange->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        // Response is 201 as usual
        $response->assertStatus(201);

        // Exactly 1 warning log line was emitted
        $records = $this->testLogHandler->getRecords();
        $warningRecords = array_values(array_filter($records, fn ($r) => $r->level === Level::Warning));

        $this->assertCount(1, $warningRecords, 'Expected exactly 1 warning log line');
        $this->assertStringContainsString('Failed to record request outcome:', $warningRecords[0]->message);
    }

    /**
     * Verify /metrics returns Prometheus format (version 0.0.4) with expected headers.
     */
    public function test_metrics_endpoint_returns_prometheus_format(): void
    {
        $repo = app(MetricCounterRepositoryInterface::class);
        $repo->increment('booking_requests_total', 'outcome="confirmed"', 5);
        $repo->incrementDuration(0.15);
        $repo->increment('idempotent_replays_total', '', 2);

        $response = $this->get('/metrics');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');

        $content = $response->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString("# TYPE booking_requests_total counter\n", $content);
        $this->assertStringContainsString('booking_requests_total{outcome="confirmed"} 5', $content);
        $this->assertStringContainsString("# TYPE booking_duration_seconds histogram\n", $content);
        $this->assertStringContainsString('booking_duration_seconds_bucket{le="0.2"} 1', $content);
        $this->assertStringContainsString('booking_duration_seconds_bucket{le="+Inf"} 1', $content);
        $this->assertStringContainsString('booking_duration_seconds_count 1', $content);
        $this->assertStringContainsString("# TYPE idempotent_replays_total counter\n", $content);
        $this->assertStringContainsString("idempotent_replays_total 2\n", $content);
        $this->assertStringEndsWith("\n", $content);
    }
}
