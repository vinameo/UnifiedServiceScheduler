<?php

namespace App\Domain\Monitoring\Services;

use App\Models\ApiEvent;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Illuminate\Support\Collection;

interface MonitoringServiceInterface
{
    /**
     * Get system monitoring summary for the specified time range.
     *
     * @return array{
     *     range: string,
     *     from: string,
     *     to: string,
     *     health: array{
     *         app: array{status: string, bookingP95Ms: int},
     *         database: array{status: string, connectionsUsed: int, connectionsMax: int},
     *         outbox: array{pending: int, oldestPendingAgeSeconds: int}
     *     },
     *     bookings: array{
     *         total: int,
     *         confirmed: int,
     *         noAvailability: int,
     *         validationError: int,
     *         error: int,
     *         p95Ms: int,
     *         allocationRetries: int,
     *         idempotentReplays: int,
     *         appointmentModified: int
     *     },
     *     recentErrors: Collection<int, ApiEvent>
     * }
     */
    public function getSummary(User $admin, string $range): array;

    /**
     * List recent errors with cursor pagination and optional correlationId search.
     *
     * @return CursorPage<ApiEvent>
     */
    public function listErrors(User $admin, ?string $correlationId, ?string $cursor, int $limit = 20): CursorPage;
}
