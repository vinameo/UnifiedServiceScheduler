<?php

namespace App\Domain\Monitoring\Repositories;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MetricCounterRepository implements MetricCounterRepositoryInterface
{
    /**
     * Prometheus duration histogram buckets in seconds (DB-08.2).
     *
     * @var list<string>
     */
    private const DURATION_BUCKETS = ['0.05', '0.1', '0.2', '0.3', '0.5', '1', '2', '5'];

    public function increment(string $name, string $labels = '', int $by = 1): void
    {
        if ($by <= 0) {
            return;
        }

        $now = CarbonImmutable::now();

        DB::statement(
            'INSERT INTO metric_counters (name, labels, value, updated_at)
             VALUES (:name, :labels, :value, :updated_at)
             ON CONFLICT (name, labels)
             DO UPDATE SET value = metric_counters.value + EXCLUDED.value, updated_at = EXCLUDED.updated_at',
            [
                'name' => $name,
                'labels' => $labels,
                'value' => $by,
                'updated_at' => $now,
            ]
        );
    }

    public function incrementDuration(float $durationSeconds): void
    {
        foreach (self::DURATION_BUCKETS as $bucket) {
            if ($durationSeconds <= (float) $bucket) {
                $this->increment('booking_duration_seconds_bucket', 'le="'.$bucket.'"');
            }
        }

        $this->increment('booking_duration_seconds_bucket', 'le="+Inf"');
        $this->increment('booking_duration_seconds_sum_ms', '', (int) round($durationSeconds * 1000));
        $this->increment('booking_duration_seconds_count', '', 1);
    }

    public function get(string $name, string $labels = ''): int
    {
        $row = DB::table('metric_counters')
            ->where('name', $name)
            ->where('labels', $labels)
            ->first(['value']);

        return $row !== null ? (int) $row->value : 0;
    }

    public function all(): Collection
    {
        /** @var Collection<int, object{name: string, labels: string, value: int}> $rows */
        $rows = DB::table('metric_counters')
            ->orderBy('name')
            ->orderBy('labels')
            ->get(['name', 'labels', 'value']);

        return $rows;
    }
}
