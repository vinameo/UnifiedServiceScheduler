<?php

namespace App\Domain\Monitoring\Services;

use App\Domain\Monitoring\Repositories\MetricCounterRepositoryInterface;

class MetricCounterService implements MetricCounterServiceInterface
{
    private const HISTOGRAM_ORDER = [
        'le="0.05"' => 0.05,
        'le="0.1"' => 0.1,
        'le="0.2"' => 0.2,
        'le="0.3"' => 0.3,
        'le="0.5"' => 0.5,
        'le="1"' => 1.0,
        'le="2"' => 2.0,
        'le="5"' => 5.0,
        'le="+Inf"' => INF,
    ];

    public function __construct(
        private readonly MetricCounterRepositoryInterface $repository,
    ) {}

    public function recordOutcome(string $outcome): void
    {
        $this->repository->increment('booking_requests_total', 'outcome="'.$outcome.'"');
    }

    public function recordDuration(float $durationSeconds): void
    {
        $this->repository->incrementDuration($durationSeconds);
    }

    public function recordReplay(): void
    {
        $this->repository->increment('idempotent_replays_total');
    }

    public function recordAllocationRetries(int $count): void
    {
        if ($count > 0) {
            $this->repository->increment('allocation_retries_total', '', $count);
        }
    }

    public function recordAppointmentModified(): void
    {
        $this->repository->increment('appointment_modified_total');
    }

    public function recordSignInFailure(): void
    {
        $this->repository->increment('sign_in_failures_total');
    }

    public function get(string $name, string $labels = ''): int
    {
        return $this->repository->get($name, $labels);
    }

    public function renderPrometheus(): string
    {
        $rows = $this->repository->all();
        $output = '';

        // 1. booking_requests_total
        $bookingRequests = $rows->where('name', 'booking_requests_total');
        $output .= "# TYPE booking_requests_total counter\n";
        foreach ($bookingRequests as $row) {
            $output .= "booking_requests_total{{$row->labels}} {$row->value}\n";
        }

        // 2. booking_duration_seconds (histogram)
        $durationBuckets = $rows->where('name', 'booking_duration_seconds_bucket');
        $sumMsRow = $rows->firstWhere('name', 'booking_duration_seconds_sum_ms');
        $countRow = $rows->firstWhere('name', 'booking_duration_seconds_count');

        if ($durationBuckets->isNotEmpty() || $sumMsRow !== null || $countRow !== null) {
            $output .= "# TYPE booking_duration_seconds histogram\n";

            // Sort buckets according to Prometheus canonical order
            $sortedBuckets = $durationBuckets->sortBy(function ($row) {
                return self::HISTOGRAM_ORDER[$row->labels] ?? 999.0;
            });

            foreach ($sortedBuckets as $bucket) {
                $output .= "booking_duration_seconds_bucket{{$bucket->labels}} {$bucket->value}\n";
            }

            $sumMs = $sumMsRow !== null ? (int) $sumMsRow->value : 0;
            $sumSeconds = sprintf('%.4f', $sumMs / 1000.0);
            // Trim unnecessary trailing zeros after decimal point if desired, but keep float representation
            $sumFormatted = rtrim(rtrim($sumSeconds, '0'), '.');
            if (! str_contains($sumFormatted, '.')) {
                $sumFormatted .= '.0';
            }

            $count = $countRow !== null ? (int) $countRow->value : 0;

            $output .= "booking_duration_seconds_sum {$sumFormatted}\n";
            $output .= "booking_duration_seconds_count {$count}\n";
        }

        // 3. Other standalone counters
        $standaloneCounters = [
            'allocation_retries_total',
            'idempotent_replays_total',
            'appointment_modified_total',
            'sign_in_failures_total',
        ];

        foreach ($standaloneCounters as $counterName) {
            $counterRow = $rows->firstWhere('name', $counterName);
            if ($counterRow !== null) {
                $output .= "# TYPE {$counterName} counter\n";
                $output .= "{$counterName} {$counterRow->value}\n";
            }
        }

        // 4. Any remaining rows not handled above
        $handledNames = array_merge(
            ['booking_requests_total', 'booking_duration_seconds_bucket', 'booking_duration_seconds_sum_ms', 'booking_duration_seconds_count'],
            $standaloneCounters
        );

        $remaining = $rows->whereNotIn('name', $handledNames)->groupBy('name');
        foreach ($remaining as $metricName => $metricRows) {
            $type = str_ends_with((string) $metricName, '_total') ? 'counter' : 'gauge';
            $output .= "# TYPE {$metricName} {$type}\n";
            foreach ($metricRows as $row) {
                if ($row->labels !== '') {
                    $output .= "{$row->name}{{$row->labels}} {$row->value}\n";
                } else {
                    $output .= "{$row->name} {$row->value}\n";
                }
            }
        }

        return $output;
    }
}
