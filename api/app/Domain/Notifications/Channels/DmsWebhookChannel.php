<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Http;
use Throwable;

class DmsWebhookChannel implements NotificationChannel
{
    public function __construct(
        private readonly ?string $webhookUrl = null,
        private readonly int $timeoutSeconds = 5,
    ) {}

    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        $url = $this->webhookUrl ?? (string) config('outbox.dms.webhook_url');
        if ($url === '') {
            throw new DeliveryFailed('DMS webhook URL is not configured.');
        }

        $eventId = (string) $message->event_id;
        $channel = $message->channel->value;
        $eventType = $message->event_type->value;

        $idempotencyKey = "{$eventId}:{$channel}";

        $payload = $message->payload;

        // D-11.5 & INV-11.5: Body carries eventId and appointment metadata; no phone or email.
        $body = [
            'eventId' => $eventId,
            'eventType' => $eventType,
            'dealershipId' => (string) $message->dealership_id,
            'appointmentId' => $message->appointment_id ? (string) $message->appointment_id : null,
            'payload' => $payload,
        ];

        foreach ($payload as $key => $value) {
            if (! array_key_exists($key, $body)) {
                $body[$key] = $value;
            }
        }

        try {
            $response = Http::withHeaders([
                'Idempotency-Key' => $idempotencyKey,
            ])
                ->timeout($this->timeoutSeconds)
                ->post($url, $body);

            if (! $response->successful()) {
                throw new DeliveryFailed(
                    "DMS webhook failed with status {$response->status()}: {$response->body()}"
                );
            }
        } catch (DeliveryFailed $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new DeliveryFailed(
                "DMS webhook delivery failed: {$e->getMessage()}",
                (int) $e->getCode(),
                $e,
            );
        }
    }
}
