<?php

namespace App\Domain\Notifications\Repositories;

use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

interface OutboxMessageRepositoryInterface
{
    /**
     * Claim up to $batch pending messages whose next_attempt_at <= $now,
     * incrementing their attempts by 1 and leasing them for 60 seconds (INV-11.3).
     *
     * @return Collection<int, OutboxMessage>
     */
    public function claimDue(CarbonImmutable $now, int $batch = 50): Collection;

    /**
     * Mark a pending message as SENT.
     */
    public function markSent(string $id, CarbonImmutable $sentAt): bool;

    /**
     * Mark a pending message as SKIPPED.
     */
    public function markSkipped(string $id, ?string $reason = null, ?CarbonImmutable $now = null): bool;

    /**
     * Mark a pending message as FAILED.
     */
    public function markFailed(string $id, string $error, ?CarbonImmutable $now = null): bool;

    /**
     * Update next_attempt_at and last_error for a retrying message.
     */
    public function scheduleRetry(string $id, CarbonImmutable $nextAttemptAt, string $error, ?CarbonImmutable $now = null): bool;

    /**
     * Find a message by id.
     */
    public function find(string $id): ?OutboxMessage;
}
