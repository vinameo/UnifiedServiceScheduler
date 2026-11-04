<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\RecipientResolverInterface;
use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use App\Support\Logging\RedactPersonalData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;

class OutboxRelay implements OutboxRelayInterface
{
    public function __construct(
        private readonly OutboxMessageRepositoryInterface $outboxRepository,
        private readonly ChannelRegistryInterface $channelRegistry,
        private readonly RecipientResolverInterface $recipientResolver,
    ) {}

    public function runOnce(CarbonImmutable $now, int $batch = 50): RelayResult
    {
        $claimedMessages = $this->outboxRepository->claimDue($now, $batch);

        $claimedCount = $claimedMessages->count();
        $sentCount = 0;
        $failedCount = 0;
        $skippedCount = 0;

        foreach ($claimedMessages as $message) {
            $recipient = $this->recipientResolver->resolve($message);
            if ($recipient === null) {
                $this->outboxRepository->markSkipped($message->id, 'NO_RECIPIENT', $now);
                $skippedCount++;

                continue;
            }

            try {
                $channel = $this->channelRegistry->get($message->channel);
                $channel->send($message, $recipient);
                $this->outboxRepository->markSent($message->id, $now);
                $sentCount++;
            } catch (DeliveryFailed $e) {
                $failedCount++;
                $redactedError = RedactPersonalData::redactString(substr($e->getMessage(), 0, 500));

                if ($message->attempts >= 5) {
                    $this->outboxRepository->markFailed($message->id, $redactedError, $now);
                } else {
                    $delaySeconds = (int) (10 * (2 ** ($message->attempts - 1)));
                    $nextAttempt = $now->addSeconds($delaySeconds);
                    $this->outboxRepository->scheduleRetry($message->id, $nextAttempt, $redactedError, $now);
                }
            }
        }

        $result = new RelayResult(
            claimed: $claimedCount,
            sent: $sentCount,
            failed: $failedCount,
            skipped: $skippedCount,
        );

        Log::info('Outbox relay processed batch', $result->toArray());

        return $result;
    }
}
