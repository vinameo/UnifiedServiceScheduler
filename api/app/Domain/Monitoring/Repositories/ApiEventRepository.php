<?php

namespace App\Domain\Monitoring\Repositories;

use App\Models\ApiEvent;
use App\Support\Pagination\CursorPage;
use App\Support\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

class ApiEventRepository implements ApiEventRepositoryInterface
{
    public function record(array $data): ApiEvent
    {
        if (! isset($data['occurred_at'])) {
            $data['occurred_at'] = CarbonImmutable::now();
        }

        if (! isset($data['detail']) || ! is_array($data['detail'])) {
            $data['detail'] = [];
        }

        if (isset($data['method']) && is_string($data['method'])) {
            $data['method'] = substr($data['method'], 0, 8);
        }

        if (isset($data['route']) && is_string($data['route'])) {
            $data['route'] = substr($data['route'], 0, 160);
        }

        if (isset($data['correlation_id']) && is_string($data['correlation_id'])) {
            $data['correlation_id'] = substr($data['correlation_id'], 0, 128);
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = substr($data['code'], 0, 48);
        }

        if (isset($data['outcome']) && is_string($data['outcome'])) {
            $data['outcome'] = substr($data['outcome'], 0, 24);
        }

        return ApiEvent::query()->create($data);
    }

    public function getBookingMetrics(?string $dealershipId, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $query = DB::table('api_events')
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<=', $to);

        if ($dealershipId !== null && $dealershipId !== '') {
            $query->where(function ($q) use ($dealershipId) {
                $q->where('dealership_id', $dealershipId)->orWhereNull('dealership_id');
            });
        } else {
            $query->whereNull('dealership_id');
        }

        $row = $query->selectRaw(<<<'SQL'
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND idempotent_replay = false) AS total,
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND outcome = 'confirmed' AND idempotent_replay = false) AS confirmed,
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND outcome = 'no_availability' AND idempotent_replay = false) AS no_availability,
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND outcome = 'validation_error' AND idempotent_replay = false) AS validation_error,
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND outcome = 'error' AND idempotent_replay = false) AS error,
            COALESCE(SUM(allocation_retries) FILTER (WHERE route LIKE '%appointments' AND method = 'POST'), 0) AS allocation_retries,
            COUNT(*) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND idempotent_replay = true) AS idempotent_replays,
            COUNT(*) FILTER (WHERE code = 'APPOINTMENT_MODIFIED') AS appointment_modified,
            percentile_cont(0.95) WITHIN GROUP (ORDER BY duration_ms) FILTER (WHERE route LIKE '%appointments' AND method = 'POST' AND idempotent_replay = false) AS p95_ms
        SQL)->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'confirmed' => (int) ($row->confirmed ?? 0),
            'noAvailability' => (int) ($row->no_availability ?? 0),
            'validationError' => (int) ($row->validation_error ?? 0),
            'error' => (int) ($row->error ?? 0),
            'p95Ms' => (int) round((float) ($row->p95_ms ?? 0)),
            'allocationRetries' => (int) ($row->allocation_retries ?? 0),
            'idempotentReplays' => (int) ($row->idempotent_replays ?? 0),
            'appointmentModified' => (int) ($row->appointment_modified ?? 0),
        ];
    }

    public function getRecentErrors(?string $dealershipId, CarbonImmutable $from, CarbonImmutable $to, int $limit = 20): Collection
    {
        $query = ApiEvent::query()
            ->where('status', '>=', 400)
            ->where('occurred_at', '>=', $from)
            ->where('occurred_at', '<=', $to);

        if ($dealershipId !== null && $dealershipId !== '') {
            $query->where(function ($q) use ($dealershipId) {
                $q->where('dealership_id', $dealershipId)->orWhereNull('dealership_id');
            });
        } else {
            $query->whereNull('dealership_id');
        }

        return $query
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit)
            ->get();
    }

    public function listErrors(?string $dealershipId, ?string $correlationId, ?string $cursor, int $limit = 20): CursorPage
    {
        $baseQuery = ApiEvent::query()->where('status', '>=', 400);

        if ($dealershipId !== null && $dealershipId !== '') {
            $baseQuery->where(function ($q) use ($dealershipId) {
                $q->where('dealership_id', $dealershipId)->orWhereNull('dealership_id');
            });
        } else {
            $baseQuery->whereNull('dealership_id');
        }

        if ($correlationId !== null && $correlationId !== '') {
            $baseQuery->where('correlation_id', $correlationId);
        }

        $total = (clone $baseQuery)->count();

        $dataQuery = clone $baseQuery;

        if ($cursor !== null && $cursor !== '') {
            $decoded = CursorPaginator::decode($cursor, ['occurred_at', 'id']);
            $cursorOccurredAt = (string) $decoded['occurred_at'];
            $cursorId = (int) $decoded['id'];

            $dataQuery->where(function ($q) use ($cursorOccurredAt, $cursorId) {
                $q->where('occurred_at', '<', $cursorOccurredAt)
                    ->orWhere(function ($sub) use ($cursorOccurredAt, $cursorId) {
                        $sub->where('occurred_at', '=', $cursorOccurredAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        $items = $dataQuery
            ->orderBy('occurred_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1)
            ->get();

        if ($items->count() > $limit) {
            $pageItems = $items->slice(0, $limit);
            $lastItem = $pageItems->last();
            $nextCursor = $lastItem !== null ? CursorPaginator::encode([
                'occurred_at' => $lastItem->occurred_at,
                'id' => $lastItem->id,
            ]) : null;
        } else {
            $pageItems = $items;
            $nextCursor = null;
        }

        return new CursorPage(
            items: $pageItems->values(),
            nextCursor: $nextCursor,
            limit: $limit,
            total: $total,
        );
    }

    public function getDatabaseHealth(): array
    {
        try {
            DB::selectOne('SELECT 1');
            $dbStatus = 'UP';
            $usedRow = DB::selectOne('SELECT count(*) as used FROM pg_stat_activity WHERE datname = current_database()');
            $connectionsUsed = (int) ($usedRow->used ?? 0);
            $maxRow = DB::selectOne('SHOW max_connections');
            $connectionsMax = (int) ($maxRow->max_connections ?? 100);
        } catch (Throwable) {
            $dbStatus = 'DOWN';
            $connectionsUsed = 0;
            $connectionsMax = 0;
        }

        return [
            'status' => $dbStatus,
            'connectionsUsed' => $connectionsUsed,
            'connectionsMax' => $connectionsMax,
        ];
    }

    public function getOutboxHealth(): array
    {
        try {
            $row = DB::selectOne(<<<'SQL'
                SELECT count(*) as pending_count, min(created_at) as oldest_created_at
                FROM outbox_messages
                WHERE status = 'PENDING'
            SQL);

            $pending = (int) ($row->pending_count ?? 0);
            $oldestPendingAgeSeconds = 0;

            if ($pending > 0 && isset($row->oldest_created_at)) {
                $oldestCreatedAt = CarbonImmutable::parse($row->oldest_created_at);
                $oldestPendingAgeSeconds = abs((int) CarbonImmutable::now()->diffInSeconds($oldestCreatedAt));
            }

            return [
                'pending' => $pending,
                'oldestPendingAgeSeconds' => $oldestPendingAgeSeconds,
            ];
        } catch (Throwable) {
            return [
                'pending' => 0,
                'oldestPendingAgeSeconds' => 0,
            ];
        }
    }
}
