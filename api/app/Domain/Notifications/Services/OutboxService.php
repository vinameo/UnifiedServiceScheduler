<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use App\Models\OutboxMessage;
use App\Models\User;
use App\Support\Pagination\CursorPage;

class OutboxService implements OutboxServiceInterface
{
    public function __construct(
        private readonly OutboxMessageRepositoryInterface $outboxMessageRepository,
    ) {}

    /**
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
    ): CursorPage {
        $dealershipId = (string) $admin->dealership_id;

        return $this->outboxMessageRepository->listForDealership(
            dealershipId: $dealershipId,
            status: $status,
            channel: $channel,
            eventType: $eventType,
            appointmentId: $appointmentId,
            cursor: $cursor,
            limit: $limit,
        );
    }

    /**
     * @return array{pending: int, retrying: int, failed: int, sentToday: int}
     */
    public function summary(User $admin): array
    {
        $dealershipId = (string) $admin->dealership_id;
        $dealership = $admin->dealership;
        $tz = $dealership !== null ? $dealership->timezone : (string) config('app.timezone', 'UTC');

        return $this->outboxMessageRepository->getSummary($dealershipId, $tz);
    }
}
