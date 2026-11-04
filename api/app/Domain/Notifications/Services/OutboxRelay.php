<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Monitoring\Repositories\ApiEventRepositoryInterface;
use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\RecipientResolverInterface;
use App\Domain\Notifications\Repositories\OutboxMessageRepositoryInterface;
use App\Models\Appointment;
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
        private readonly AppointmentRepositoryInterface $appointmentRepository,
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

        /** @var array<string, ?Appointment> $appointmentCache */
        $appointmentCache = [];

        foreach ($claimedMessages as $message) {
            // Step 2.1: Relevance check (D-11.7)
            $skipReason = $this->checkRelevance($message, $appointmentCache);
            if ($skipReason !== null) {
                $this->outboxRepository->markSkipped($message->id, $skipReason, $now);
                $skippedCount++;

                continue;
            }

            // Step 2.2: Recipient resolution (D-11.4)
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

    /**
     * D-11.7: Before sending, check relevance:
     * AppointmentConfirmed, AppointmentRescheduled, AppointmentReminder for a booking
     * that is no longer CONFIRMED, or whose start differs from the payload (e.g. rescheduled
     * to a different time), are marked SKIPPED (not sent, with appropriate reason e.g.
     * 'NO_LONGER_CONFIRMED' or 'START_TIME_CHANGED').
     * AppointmentCancelled and AppointmentReassigned are always sent.
     *
     * @param  array<string, ?Appointment>  $appointmentCache
     */
    private function checkRelevance(OutboxMessage $message, array &$appointmentCache): ?string
    {
        if (! in_array($message->event_type, [
            OutboxEventType::AppointmentConfirmed,
            OutboxEventType::AppointmentRescheduled,
            OutboxEventType::AppointmentReminder,
        ], true)) {
            return null;
        }

        $appointmentId = $message->appointment_id ?? ($message->payload['appointmentId'] ?? null);
        if ($appointmentId === null) {
            return 'NO_LONGER_CONFIRMED';
        }

        $appointmentId = (string) $appointmentId;
        if (! array_key_exists($appointmentId, $appointmentCache)) {
            $appointmentCache[$appointmentId] = $this->appointmentRepository->find($appointmentId);
        }

        $appointment = $appointmentCache[$appointmentId];
        if ($appointment === null || $appointment->status !== AppointmentStatus::CONFIRMED) {
            return 'NO_LONGER_CONFIRMED';
        }

        $payload = $message->payload;
        if (isset($payload['start'])) {
            $payloadStart = CarbonImmutable::parse((string) $payload['start']);
            $appointmentStart = CarbonImmutable::parse($appointment->start_at);
            if ($payloadStart->ne($appointmentStart)) {
                return 'START_TIME_CHANGED';
            }
        }

        return null;
    }
}
