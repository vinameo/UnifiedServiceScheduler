<?php

namespace Tests\Feature\Monitoring;

use App\Domain\Monitoring\Repositories\MetricCounterRepositoryInterface;
use App\Models\ApiEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\TestCase;

class PruneTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-08-12 (AC-08.11; Integration):
     * given api_events 8 days and 6 days old;
     * when monitoring:prune;
     * then The 8-day row is deleted; the 6-day row stays.
     */
    public function test_AC_08_11_events_older_than_7_days_are_pruned(): void
    {
        $now = CarbonImmutable::now();

        // 8 days old event (should be pruned)
        $event8Days = ApiEvent::query()->create([
            'occurred_at' => $now->subDays(8),
            'correlation_id' => 'corr-8-days-'.Str::uuid(),
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 201,
            'outcome' => 'confirmed',
            'duration_ms' => 150,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['test' => '8-days-old'],
        ]);

        // 6 days old event (should stay)
        $event6Days = ApiEvent::query()->create([
            'occurred_at' => $now->subDays(6),
            'correlation_id' => 'corr-6-days-'.Str::uuid(),
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 201,
            'outcome' => 'confirmed',
            'duration_ms' => 120,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => ['test' => '6-days-old'],
        ]);

        // Metric counter before prune (INV-08.3: cumulative counters never deleted)
        /** @var MetricCounterRepositoryInterface $counterRepo */
        $counterRepo = app(MetricCounterRepositoryInterface::class);
        $counterRepo->increment('booking_requests_total', 'outcome="confirmed"', 5);

        $exitCode = Artisan::call('monitoring:prune');
        $this->assertSame(0, $exitCode);

        // 8-day event is deleted
        $this->assertDatabaseMissing('api_events', [
            'id' => $event8Days->id,
        ]);

        // 6-day event stays
        $this->assertDatabaseHas('api_events', [
            'id' => $event6Days->id,
        ]);

        // Counter is untouched (INV-08.3)
        $this->assertDatabaseHas('metric_counters', [
            'name' => 'booking_requests_total',
            'labels' => 'outcome="confirmed"',
            'value' => 5,
        ]);
    }

    public function test_custom_days_option(): void
    {
        $now = CarbonImmutable::now();

        $event4Days = ApiEvent::query()->create([
            'occurred_at' => $now->subDays(4),
            'correlation_id' => 'corr-4-days-'.Str::uuid(),
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 201,
            'outcome' => 'confirmed',
            'duration_ms' => 100,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => [],
        ]);

        $event2Days = ApiEvent::query()->create([
            'occurred_at' => $now->subDays(2),
            'correlation_id' => 'corr-2-days-'.Str::uuid(),
            'method' => 'POST',
            'route' => 'POST api/v1/appointments',
            'status' => 201,
            'outcome' => 'confirmed',
            'duration_ms' => 100,
            'idempotent_replay' => false,
            'allocation_retries' => 0,
            'detail' => [],
        ]);

        $exitCode = Artisan::call('monitoring:prune', ['--days' => 3]);
        $this->assertSame(0, $exitCode);

        $this->assertDatabaseMissing('api_events', ['id' => $event4Days->id]);
        $this->assertDatabaseHas('api_events', ['id' => $event2Days->id]);
    }

    public function test_invalid_days_option_returns_failure(): void
    {
        $exitCode = Artisan::call('monitoring:prune', ['--days' => 0]);
        $this->assertSame(1, $exitCode);

        $exitCodeNegative = Artisan::call('monitoring:prune', ['--days' => -5]);
        $this->assertSame(1, $exitCodeNegative);

        $exitCodeString = Artisan::call('monitoring:prune', ['--days' => 'abc']);
        $this->assertSame(1, $exitCodeString);
    }
}
