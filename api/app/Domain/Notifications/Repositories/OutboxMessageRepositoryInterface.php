<?php

namespace App\Domain\Notifications\Repositories;

use App\Domain\Notifications\Exceptions\OutboxMessageNotFound;
use App\Domain\Notifications\Exceptions\OutboxNotRetryable;
use App\Models\OutboxMessage;
use App\Support\Pagination\CursorPage;
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
     * Fail abandoned pending messages whose attempts >= 5 and next_attempt_at <= $now (INV-11.4).
     *
     * @return Collection<int, OutboxMessage>
     */
    public function failAbandoned(CarbonImmutable $now, int $batch = 50): Collection;

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

    /**
     * Insert an outbox message row with ON CONFLICT (dedupe_key) DO NOTHING (D-11.6, INV-11.7).
     * Returns true if row was inserted, false if ignored due to unique dedupe_key conflict.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function insertIfAbsent(array $attributes): bool;

    /**
     * List outbox messages for a dealership with optional filters and cursor pagination.
     *
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
    ): CursorPage;

    /**
     * Get summary counts for a dealership: pending, retrying, failed, sentToday.
     *
     * @return array{pending: int, retrying: int, failed: int, sentToday: int}
     */
    public function getSummary(string $dealershipId, string $timezone = 'UTC'): array;

    /**
     * Find a message by id scoped to dealership.
     */
    public function findForDealership(string $id, string $dealershipId): ?OutboxMessage;

    /**
     * Retry a failed outbox message, resetting its status to PENDING, attempts to 0,
     * next_attempt_at to now, and clearing last_error (RQ §B7 Retry, D-11.8, INV-11.6).
     *
     * @throws OutboxMessageNotFound
     * @throws OutboxNotRetryable
     */
    public function retry(string $dealershipId, string $messageId, CarbonImmutable $now): OutboxMessage;
}
