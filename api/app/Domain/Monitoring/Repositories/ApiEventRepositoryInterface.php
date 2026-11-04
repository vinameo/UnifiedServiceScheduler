<?php

namespace App\Domain\Monitoring\Repositories;

use App\Models\ApiEvent;
use App\Support\Pagination\CursorPage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface ApiEventRepositoryInterface
{
    /**
     * Persist an API event record.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(array $data): ApiEvent;

    /**
     * Aggregated booking metrics from api_events within the given time range.
     *
     * @return array{
     *     total: int,
     *     confirmed: int,
     *     noAvailability: int,
     *     validationError: int,
     *     error: int,
     *     p95Ms: int,
     *     allocationRetries: int,
     *     idempotentReplays: int,
     *     appointmentModified: int
     * }
     */
    public function getBookingMetrics(?string $dealershipId, CarbonImmutable $from, CarbonImmutable $to): array;

    /**
     * Get recent errors (status >= 400), newest first, within range, scoped to dealership.
     *
     * @return Collection<int, ApiEvent>
     */
    public function getRecentErrors(?string $dealershipId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 20): Collection;

    /**
     * List errors with cursor pagination and optional correlationId search.
     *
     * @return CursorPage<ApiEvent>
     */
    public function listErrors(?string $dealershipId, ?string $correlationId, ?string $cursor, int $limit = 20): CursorPage;

    /**
     * Probe database health and connection usage.
     *
     * @return array{
     *     status: string,
     *     connectionsUsed: int,
     *     connectionsMax: int
     * }
     */
    public function getDatabaseHealth(): array;

    /**
     * Probe transactional outbox health.
     *
     * @return array{
     *     pending: int,
     *     oldestPendingAgeSeconds: int
     * }
     */
    public function getOutboxHealth(): array;

    /**
     * Delete API events that occurred before the given cutoff date/time.
     */
    public function pruneOlderThan(CarbonImmutable $cutoff): int;

    /**
     * Delete API events older than the given cutoff date/time or number of days (default 7 days).
     */
    public function prune(int|CarbonImmutable $cutoffOrDays = 7): int;
}
