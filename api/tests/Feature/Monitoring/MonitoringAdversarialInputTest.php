<?php

namespace Tests\Feature\Monitoring;

use App\Models\ApiEvent;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class MonitoringAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-08-17 (API §B4; Integration):
     * when Each row of the adversarial input table;
     * then As the table.
     */
    public function test_TC_08_17_adversarial_input_table(): void
    {
        // 1. Unauthenticated requests -> 401
        $this->getJson('/api/v1/monitoring/summary?range=1h')->assertStatus(401);
        $this->getJson('/api/v1/monitoring/errors')->assertStatus(401);

        $world = World::saigonD7();
        $admin = $world->admin();
        $advisor = $world->advisor();
        $foreman = $world->foreman();
        $customer = User::factory()->customer()->create();

        // 2. Endpoint 08.1: range=30d -> 400
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/summary?range=30d');
        $res->assertStatus(400);

        // 3. Endpoint 08.1: missing range -> 400
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/summary');
        $res->assertStatus(400);

        // 4. Endpoint 08.1: non-admin roles (advisor, foreman, customer) -> 403
        $this->actingAs($advisor)->getJson('/api/v1/monitoring/summary?range=24h')->assertStatus(403);
        $this->actingAs($foreman)->getJson('/api/v1/monitoring/summary?range=24h')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/v1/monitoring/summary?range=24h')->assertStatus(403);

        // 5. Endpoint 08.2: correlationId > 128 characters -> 400
        $longCorrelationId = str_repeat('a', 129);
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?correlationId='.$longCorrelationId);
        $res->assertStatus(400);

        // 6. Endpoint 08.2: correlationId with characters outside [A-Za-z0-9._-] -> 400
        $badChars = ['bad id with spaces', 'bad$id', 'bad<script>', 'bad;drop table', 'bad!@#'];
        foreach ($badChars as $badId) {
            $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?correlationId='.urlencode($badId));
            $res->assertStatus(400);
        }

        // 7. Endpoint 08.2: correlationId of an error at another dealership -> 200, data: []
        $otherDealership = Dealership::factory()->create([
            'code' => 'OTHER-DLR',
            'name' => 'Other Dealership',
            'branch_name' => 'Other Branch',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $otherCorrId = 'foreign-corr-'.Str::uuid();
        ApiEvent::query()->create([
            'occurred_at' => CarbonImmutable::now()->subMinutes(10),
            'correlation_id' => $otherCorrId,
            'dealership_id' => $otherDealership->id,
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 500,
            'code' => 'ERROR',
            'outcome' => 'error',
            'duration_ms' => 150,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['message' => 'Foreign dealership error'],
        ]);

        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?correlationId='.$otherCorrId);
        $res->assertStatus(200);
        $res->assertJsonPath('data', []);

        // 8. Endpoint 08.2: non-admin roles (advisor, foreman, customer) -> 403
        $this->actingAs($advisor)->getJson('/api/v1/monitoring/errors')->assertStatus(403);
        $this->actingAs($foreman)->getJson('/api/v1/monitoring/errors')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/v1/monitoring/errors')->assertStatus(403);

        // 9. Endpoint 08.2: invalid cursor -> 400
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?cursor=invalid_base64_or_format!');
        $res->assertStatus(400);

        // 10. Endpoint 08.2: limit out of bounds -> 400
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?limit=101');
        $res->assertStatus(400);
        $res = $this->actingAs($admin)->getJson('/api/v1/monitoring/errors?limit=0');
        $res->assertStatus(400);
    }
}
