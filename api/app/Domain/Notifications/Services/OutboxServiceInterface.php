<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Exceptions\OutboxMessageNotFound;
use App\Domain\Notifications\Exceptions\OutboxNotRetryable;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Support\Pagination\CursorPage;
use Carbon\CarbonImmutable;

interface OutboxServiceInterface
{
    /**
     * List outbox messages visible to the admin.
     *
     * @return CursorPage<OutboxMessage>
     */
    public function list(
        User $admin,
        ?string $status = null,
        ?string $channel = null,
        ?string $eventType = null,
        ?string $appointmentId = null,
        ?string $cursor = null,
        int $limit = 50,
    ): CursorPage;

    /**
     * Get summary counts of outbox messages for the admin's dealership.
     *
     * @return array{pending: int, retrying: int, failed: int, sentToday: int}
     */
    public function summary(User $admin): array;

    /**
     * Retry a failed outbox message for a dealership (RQ §B7 Retry, D-11.8, INV-11.6).
     *
     * @throws OutboxMessageNotFound
     * @throws OutboxNotRetryable
     */
    public function retry(string $dealershipId, string $messageId, CarbonImmutable $now): OutboxMessage;
}
