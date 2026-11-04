<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\RecipientResolverInterface;
use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use App\Models\OutboxMessage;
use App\Support\Logging\RedactPersonalData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

class OutboxRelay implements OutboxRelayInterface
{
    public function __construct(
        private readonly OutboxMessageRepositoryInterface $outboxRepository,
        private readonly ChannelRegistryInterface $channelRegistry,
        private readonly RecipientResolverInterface $recipientResolver,
        private readonly ApiEventRepositoryInterface $apiEventRepository,
    ) {}

    public function runOnce(CarbonImmutable $now, int $batch = 50): RelayResult
    {
        // 0. failAbandoned (INV-11.4)
        $abandonedMessages = $this->outboxRepository->failAbandoned($now, $batch);
        foreach ($abandonedMessages as $abandoned) {
            $this->recordDeliveryFailedEvent($abandoned, 'NO_RESULT', $now);
        }

        // 1. Claim due pending rows
        $claimedMessages = $this->outboxRepository->claimDue($now, $batch);

        $claimedCount = $claimedMessages->count();
        $sentCount = 0;
        $failedCount = $abandonedMessages->count();
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
                $redactedError = mb_substr(RedactPersonalData::redactString($e->getMessage()), 0, 500);

                if ($message->attempts >= 5) {
                    $this->outboxRepository->markFailed($message->id, $redactedError, $now);
                    $this->recordDeliveryFailedEvent($message, $redactedError, $now);
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

    private function recordDeliveryFailedEvent(OutboxMessage $message, string $error, CarbonImmutable $now): void
    {
        try {
            $payload = $message->payload;
            $reference = isset($payload['reference']) ? (string) $payload['reference'] : 'unknown';

            $channelLabel = match ($message->channel) {
                OutboxChannel::SMS => 'SMS',
                OutboxChannel::EMAIL => 'email',
                OutboxChannel::DMS => 'DMS webhook',
            };

            $eventAction = match ($message->event_type) {
                OutboxEventType::AppointmentConfirmed => 'Confirmation',
                OutboxEventType::AppointmentCancelled => 'Cancellation',
                OutboxEventType::AppointmentRescheduled => 'Reschedule',
                OutboxEventType::AppointmentReassigned => 'Reassignment',
                OutboxEventType::AppointmentReminder => 'Reminder',
            };

            $eventDesc = "{$eventAction} {$channelLabel}";

            $this->apiEventRepository->record([
                'occurred_at' => $now,
                'correlation_id' => (string) $message->event_id,
                'dealership_id' => $message->dealership_id,
                'user_id' => null,
                'appointment_id' => $message->appointment_id,
                'method' => '-',
                'route' => 'outbox-relay',
                'status' => null,
                'code' => 'DELIVERY_FAILED',
                'outcome' => 'error',
                'duration_ms' => null,
                'idempotent_replay' => false,
                'allocation_retries' => 0,
                'detail' => [
                    'event' => $eventDesc,
                    'reference' => $reference,
                    'channel' => $message->channel->value,
                    'error' => $error,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record DELIVERY_FAILED event: '.$e->getMessage());
        }
    }
}
