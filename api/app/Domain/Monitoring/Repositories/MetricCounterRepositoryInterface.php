<?php

namespace App\Domain\Monitoring\Repositories;

use Illuminate\Support\Collection;

interface MetricCounterRepositoryInterface
{
    /**
     * Atomically increment a counter.
     */
    public function increment(string $name, string $labels = '', int $by = 1): void;

    /**
     * Increment duration histogram buckets, sum_ms, and count.
     */
    public function incrementDuration(float $durationSeconds): void;

    /**
     * Get the current value of a counter.
     */
    public function get(string $name, string $labels = ''): int;

    /**
     * Get all counters.
     *
     * @return Collection<int, object{name: string, labels: string, value: int}>
     */
    public function all(): Collection;
}
