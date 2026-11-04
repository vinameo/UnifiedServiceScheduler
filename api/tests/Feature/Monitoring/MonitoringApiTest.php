<?php

namespace Tests\Feature\Monitoring;

use App\Models\ApiEvent;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class MonitoringApiTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-08-04 (AC-08.4; Integration):
     * given Advisor, foreman, customer;
     * when GET /monitoring/summary?range=1h;
     * then 403 for all three; admin 200.
     */
    public function test_AC_08_4_admin_only(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $foreman = $world->foreman();
        $customer = User::factory()->customer()->create();
        $admin = $world->admin();

        $advisorResponse = $this->actingAs($advisor)->getJson('/api/v1/monitoring/summary?range=1h');
        $advisorResponse->assertStatus(403);

        $foremanResponse = $this->actingAs($foreman)->getJson('/api/v1/monitoring/summary?range=1h');
        $foremanResponse->assertStatus(403);

        $customerResponse = $this->actingAs($customer)->getJson('/api/v1/monitoring/summary?range=1h');
        $customerResponse->assertStatus(403);

        $adminResponse = $this->actingAs($admin)->getJson('/api/v1/monitoring/summary?range=1h');
        $adminResponse->assertStatus(200);
        $adminResponse->assertJsonStructure([
            'data' => [
                'range',
                'from',
                'to',
                'health' => [
                    'app' => ['status', 'bookingP95Ms'],
                    'database' => ['status', 'connectionsUsed', 'connectionsMax'],
                    'outbox' => ['pending', 'oldestPendingAgeSeconds'],
                ],
                'bookings' => [
                    'total',
                    'confirmed',
                    'noAvailability',
                    'validationError',
                    'error',
                    'p95Ms',
                    'allocationRetries',
                    'idempotentReplays',
                    'appointmentModified',
                ],
                'recentErrors',
            ],
        ]);
    }

    /**
     * TC-08-09 (AC-08.8, D-08.9–08.11; Integration):
     * given Admin of D1; api_events of D1 (2), D2 (3), NULL (1); 2 PENDING outbox rows;
     * when summary?range=24h;
     * then recentErrors has 3 rows (D1 + NULL); outbox.pending 2; database.status UP, connectionsMax > 0.
     */
    public function test_AC_08_8_summary_is_scoped(): void
    {
        $world = World::saigonD7();
        $d1 = $world->dealership;
        $admin = $world->admin();

        $d2 = Dealership::factory()->create([
            'code' => 'D2-TEST',
            'name' => 'Dealership 2',
            'branch_name' => 'Branch 2',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);

        $now = CarbonImmutable::now();

        // D1: 2 error events
        for ($i = 0; $i < 2; $i++) {
            ApiEvent::query()->create([
                'occurred_at' => $now->subMinutes(10 * ($i + 1)),
                'correlation_id' => 'd1-err-'.$i.'-'.Str::uuid(),
                'dealership_id' => $d1->id,
                'method' => 'POST',
                'route' => 'POST api/v1/appointments',
                'status' => 409,
                'code' => 'NO_AVAILABILITY',
                'outcome' => 'no_availability',
                'duration_ms' => 120,
                'idempotent_replay' => false,
                'allocation_retries' => 0,
                'detail' => ['reason' => 'Bay busy'],
            ]);
        }

        // D2: 3 error events
        for ($i = 0; $i < 3; $i++) {
            ApiEvent::query()->create([
                'occurred_at' => $now->subMinutes(15 * ($i + 1)),
                'correlation_id' => 'd2-err-'.$i.'-'.Str::uuid(),
                'dealership_id' => $d2->id,
                'method' => 'POST',
                'route' => 'POST api/v1/appointments',
                'status' => 500,
                'code' => 'INTERNAL_ERROR',
                'outcome' => 'error',
                'duration_ms' => 200,
                'idempotent_replay' => false,
                'allocation_retries' => 0,
                'detail' => ['message' => 'D2 error'],
            ]);
        }

        // NULL dealership_id: 1 error event
        ApiEvent::query()->create([
            'occurred_at' => $now->subMinutes(5),
            'correlation_id' => 'null-err-'.Str::uuid(),
            'dealership_id' => null,
            'method' => 'GET',
            'route' => 'GET api/v1/health',
            'status' => 503,
            'code' => 'SERVICE_UNAVAILABLE',
            'outcome' => 'error',
            'duration_ms' => 50,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['message' => 'System error'],
        ]);

        // 2 PENDING outbox messages
        DB::table('outbox_messages')->insert([
            [
                'id' => (string) Str::uuid(),
                'event_id' => (string) Str::uuid(),
                'dealership_id' => $d1->id,
                'appointment_id' => null,
                'event_type' => 'AppointmentConfirmed',
                'channel' => 'EMAIL',
                'payload' => json_encode(['text' => 'msg 1']),
                'status' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now->subMinutes(7),
                'updated_at' => $now->subMinutes(7),
            ],
            [
                'id' => (string) Str::uuid(),
                'event_id' => (string) Str::uuid(),
                'dealership_id' => $d1->id,
                'appointment_id' => null,
                'event_type' => 'AppointmentConfirmed',
                'channel' => 'SMS',
                'payload' => json_encode(['text' => 'msg 2']),
                'status' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now->subMinutes(5),
                'updated_at' => $now->subMinutes(5),
            ],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/monitoring/summary?range=24h');
        $response->assertStatus(200);

        $recentErrors = $response->json('data.recentErrors');
        $this->assertCount(3, $recentErrors, 'recentErrors must contain exactly 3 rows (2 from D1 + 1 from NULL dealership)');

        $correlationIds = array_column($recentErrors, 'correlationId');
        foreach ($correlationIds as $cid) {
            $this->assertStringStartsNotWith('d2-err-', $cid, 'Errors from D2 must not leak into D1 summary');
        }

        $this->assertSame(2, $response->json('data.health.outbox.pending'));
        $this->assertGreaterThan(0, $response->json('data.health.outbox.oldestPendingAgeSeconds'));
        $this->assertSame('UP', $response->json('data.health.database.status'));
        $this->assertGreaterThan(0, $response->json('data.health.database.connectionsMax'));
    }

    /**
     * TC-08-10 (AC-08.9; Integration):
     * given An error with correlation X;
     * when errors?correlationId=X;
     * then 1 row.
     */
    public function test_AC_08_9_search_by_correlation_id(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();

        $correlationX = 'corr-X-'.Str::uuid();
        $correlationY = 'corr-Y-'.Str::uuid();

        ApiEvent::query()->create([
            'occurred_at' => CarbonImmutable::now()->subMinutes(10),
            'correlation_id' => $correlationX,
            'dealership_id' => $world->dealership->id,
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 409,
            'code' => 'NO_AVAILABILITY',
            'outcome' => 'no_availability',
            'duration_ms' => 120,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['reason' => 'No bay free'],
        ]);

        ApiEvent::query()->create([
            'occurred_at' => CarbonImmutable::now()->subMinutes(5),
            'correlation_id' => $correlationY,
            'dealership_id' => $world->dealership->id,
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 500,
            'code' => 'ERROR',
            'outcome' => 'error',
            'duration_ms' => 200,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['message' => 'Another error'],
        ]);

        $response = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?correlationId='.$correlationX);
        $response->assertStatus(200);

        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($correlationX, $data[0]['correlationId']);
        $this->assertSame(409, $data[0]['status']);
        $this->assertSame('NO_AVAILABILITY', $data[0]['code']);
    }
}
