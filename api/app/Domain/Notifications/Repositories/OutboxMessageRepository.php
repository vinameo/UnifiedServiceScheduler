<?php

namespace App\Domain\Notifications\Repositories;

use App\Models\OutboxMessage;
use App\Support\Pagination\CursorPage;
use App\Support\Pagination\CursorPaginator;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OutboxMessageRepository implements OutboxMessageRepositoryInterface
{
    /**
     * @return Collection<int, OutboxMessage>
     */
    public function claimDue(CarbonImmutable $now, int $batch = 50): Collection
    {
        $now = $now->utc();

        return DB::transaction(function () use ($now, $batch) {
            /** @var Collection<int, OutboxMessage> $rows */
            $rows = OutboxMessage::query()
                ->where('status', 'PENDING')
                ->where('attempts', '<', 5)
                ->where('next_attempt_at', '<=', $now)
                ->orderBy('next_attempt_at')
                ->limit($batch)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            $ids = $rows->pluck('id')->all();
            $leaseExpiresAt = $now->addSeconds(60);

            DB::table('outbox_messages')
                ->whereIn('id', $ids)
                ->update([
                    'attempts' => DB::raw('attempts + 1'),
                    'next_attempt_at' => $leaseExpiresAt->toIso8601String(),
                    'updated_at' => $now->toIso8601String(),
                ]);

            foreach ($rows as $row) {
                $row->attempts += 1;
                $row->next_attempt_at = $leaseExpiresAt;
            }

            return $rows;
        });
    }

    /**
     * @return Collection<int, OutboxMessage>
     */
    public function failAbandoned(CarbonImmutable $now, int $batch = 50): Collection
    {
        $now = $now->utc();

        return DB::transaction(function () use ($now, $batch) {
            /** @var Collection<int, OutboxMessage> $rows */
            $rows = OutboxMessage::query()
                ->where('status', 'PENDING')
                ->where('attempts', '>=', 5)
                ->where('next_attempt_at', '<=', $now)
                ->orderBy('next_attempt_at')
                ->limit($batch)
                ->lock('FOR UPDATE SKIP LOCKED')
                ->get();

            if ($rows->isEmpty()) {
                return collect();
            }

            $ids = $rows->pluck('id')->all();

            DB::table('outbox_messages')
                ->whereIn('id', $ids)
                ->update([
                    'status' => 'FAILED',
                    'last_error' => 'NO_RESULT',
                    'updated_at' => $now->toIso8601String(),
                ]);

            foreach ($rows as $row) {
                $row->status = 'FAILED';
                $row->last_error = 'NO_RESULT';
            }

            return $rows;
        });
    }

    public function markSent(string $id, CarbonImmutable $sentAt): bool
    {
        $sentAt = $sentAt->utc();
        $affected = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('status', 'PENDING')
            ->update([
                'status' => 'SENT',
                'sent_at' => $sentAt->toIso8601String(),
                'updated_at' => $sentAt->toIso8601String(),
            ]);

        return $affected > 0;
    }

    public function markSkipped(string $id, ?string $reason = null, ?CarbonImmutable $now = null): bool
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $affected = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('status', 'PENDING')
            ->update([
                'status' => 'SKIPPED',
                'last_error' => $reason,
                'updated_at' => $now->toIso8601String(),
            ]);

        return $affected > 0;
    }

    public function markFailed(string $id, string $error, ?CarbonImmutable $now = null): bool
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $affected = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('status', 'PENDING')
            ->update([
                'status' => 'FAILED',
                'last_error' => $error,
                'updated_at' => $now->toIso8601String(),
            ]);

        return $affected > 0;
    }

    public function scheduleRetry(string $id, CarbonImmutable $nextAttemptAt, string $error, ?CarbonImmutable $now = null): bool
    {
        $now = ($now ?? CarbonImmutable::now())->utc();
        $nextAttemptAt = $nextAttemptAt->utc();
        $affected = DB::table('outbox_messages')
            ->where('id', $id)
            ->where('status', 'PENDING')
            ->update([
                'next_attempt_at' => $nextAttemptAt->toIso8601String(),
                'last_error' => $error,
                'updated_at' => $now->toIso8601String(),
            ]);

        return $affected > 0;
    }

    public function find(string $id): ?OutboxMessage
    {
        return OutboxMessage::query()->find($id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function insertIfAbsent(array $attributes): bool
    {
        $sql = <<<'SQL'
            INSERT INTO outbox_messages (
                id, event_id, dealership_id, appointment_id, event_type, channel,
                payload, status, attempts, next_attempt_at, dedupe_key, created_at, updated_at
            ) VALUES (
                :id, :event_id, :dealership_id, :appointment_id, :event_type, :channel,
                CAST(:payload AS jsonb), :status, :attempts, :next_attempt_at, :dedupe_key, :created_at, :updated_at
            ) ON CONFLICT (dedupe_key) DO NOTHING
        SQL;

        $payload = $attributes['payload'];
        $payloadJson = is_string($payload) ? $payload : json_encode($payload, JSON_THROW_ON_ERROR);

        $affected = DB::affectingStatement($sql, [
            'id' => $attributes['id'],
            'event_id' => $attributes['event_id'],
            'dealership_id' => $attributes['dealership_id'],
            'appointment_id' => $attributes['appointment_id'] ?? null,
            'event_type' => $attributes['event_type'],
            'channel' => $attributes['channel'],
            'payload' => $payloadJson,
            'status' => $attributes['status'] ?? 'PENDING',
            'attempts' => $attributes['attempts'] ?? 0,
            'next_attempt_at' => $attributes['next_attempt_at'],
            'dedupe_key' => $attributes['dedupe_key'] ?? null,
            'created_at' => $attributes['created_at'],
            'updated_at' => $attributes['updated_at'],
        ]);

        return $affected > 0;
    }

    /**
     * @return CursorPage<OutboxMessage>
     */
    public function listForDealership(
        string $dealershipId,
        ?string $status = null,
        ?string $channel = null,
        ?string $eventType = null,
        ?string $appointmentId = null,
        ?string $cursor = null,
        int $limit = 50,
    ): CursorPage {
        $baseQuery = OutboxMessage::query()->where('dealership_id', $dealershipId);

        if ($status !== null && $status !== '') {
            if ($status === 'PENDING') {
                $baseQuery->where('status', 'PENDING')->where('attempts', 0);
            } elseif ($status === 'RETRYING') {
                $baseQuery->where('status', 'PENDING')->where('attempts', '>', 0);
            } else {
                $baseQuery->where('status', $status);
            }
        }

        if ($channel !== null && $channel !== '') {
            $baseQuery->where('channel', $channel);
        }

        if ($eventType !== null && $eventType !== '') {
            $baseQuery->where('event_type', $eventType);
        }

        if ($appointmentId !== null && $appointmentId !== '') {
            $baseQuery->where('appointment_id', $appointmentId);
        }

        $total = (clone $baseQuery)->count();

        $dataQuery = clone $baseQuery;

        if ($cursor !== null && $cursor !== '') {
            $decoded = CursorPaginator::decode($cursor, ['created_at', 'id']);
            $cursorCreatedAt = (string) $decoded['created_at'];
            $cursorId = (string) $decoded['id'];

            $dataQuery->where(function ($q) use ($cursorCreatedAt, $cursorId) {
                $q->where('created_at', '<', $cursorCreatedAt)
                    ->orWhere(function ($sub) use ($cursorCreatedAt, $cursorId) {
                        $sub->where('created_at', '=', $cursorCreatedAt)
                            ->where('id', '<', $cursorId);
                    });
            });
        }

        /** @var Collection<int, OutboxMessage> $items */
        $items = $dataQuery
            ->with(['dealership', 'appointment.customer'])
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($limit + 1)
            ->get();

        if ($items->count() > $limit) {
            $pageItems = $items->slice(0, $limit);
            $lastItem = $pageItems->last();
            $nextCursor = $lastItem !== null ? CursorPaginator::encode([
                'created_at' => $lastItem->created_at,
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

    /**
     * @return array{pending: int, retrying: int, failed: int, sentToday: int}
     */
    public function getSummary(string $dealershipId, string $timezone = 'UTC'): array
    {
        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Throwable) {
            $tz = new DateTimeZone('UTC');
        }

        $now = CarbonImmutable::now($tz);
        $todayStart = $now->startOfDay()->utc();
        $todayEnd = $now->endOfDay()->utc();

        $row = DB::table('outbox_messages')
            ->where('dealership_id', $dealershipId)
            ->selectRaw(<<<'SQL'
                COUNT(*) FILTER (WHERE status = 'PENDING' AND attempts = 0) AS pending,
                COUNT(*) FILTER (WHERE status = 'PENDING' AND attempts > 0) AS retrying,
                COUNT(*) FILTER (WHERE status = 'FAILED') AS failed,
                COUNT(*) FILTER (WHERE status = 'SENT' AND sent_at >= ? AND sent_at <= ?) AS sent_today
            SQL, [$todayStart->toIso8601String(), $todayEnd->toIso8601String()])
            ->first();

        return [
            'pending' => (int) ($row->pending ?? 0),
            'retrying' => (int) ($row->retrying ?? 0),
            'failed' => (int) ($row->failed ?? 0),
            'sentToday' => (int) ($row->sent_today ?? 0),
        ];
    }

    public function findForDealership(string $id, string $dealershipId): ?OutboxMessage
    {
        return OutboxMessage::query()
            ->where('dealership_id', $dealershipId)
            ->where('id', $id)
            ->first();
    }
}
