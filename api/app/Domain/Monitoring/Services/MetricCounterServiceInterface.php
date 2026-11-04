<?php

namespace App\Domain\Monitoring\Services;

interface MetricCounterServiceInterface
{
    /**
     * Increment booking_requests_total for the given outcome.
     */
    public function recordOutcome(string $outcome): void;

    /**
     * Increment duration histogram for booking requests.
     */
    public function recordDuration(float $durationSeconds): void;

    /**
     * Increment idempotent_replays_total counter.
     */
    public function recordReplay(): void;

    /**
     * Increment allocation_retries_total counter by count.
     */
    public function recordAllocationRetries(int $count): void;

    /**
     * Increment appointment_modified_total counter.
     */
    public function recordAppointmentModified(): void;

    /**
     * Increment sign_in_failures_total counter.
     */
    public function recordSignInFailure(): void;

    /**
     * Get current value of a counter.
     */
    public function get(string $name, string $labels = ''): int;

    /**
     * Render all metric counters in Prometheus text format (version 0.0.4).
     */
    public function renderPrometheus(): string;
}
