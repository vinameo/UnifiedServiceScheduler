<?php

namespace App\Domain\Notifications\Repositories;

use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class OutboxMessageRepository implements OutboxMessageRepositoryInterface
{
    /**
     * @return Collection<int, OutboxMessage>
     */
    public function claimDue(CarbonImmutable $now, int $batch = 50): Collection
    {
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

    public function markSent(string $id, CarbonImmutable $sentAt): bool
    {
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
        $now = $now ?? CarbonImmutable::now();
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
        $now = $now ?? CarbonImmutable::now();
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
        $now = $now ?? CarbonImmutable::now();
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
}
