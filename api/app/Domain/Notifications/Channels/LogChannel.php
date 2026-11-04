<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;

class LogChannel implements NotificationChannel
{
    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        $channel = $message->channel->value;
        $eventType = $message->event_type->value;

        // D-11.3 & INV-11.5: One JSON log line, no personal data (no phone, no email, no recipient name).
        Log::info("Notification sent via {$channel}", [
            'channel' => $channel,
            'eventId' => (string) $message->event_id,
            'eventType' => $eventType,
            'dealershipId' => (string) $message->dealership_id,
            'appointmentId' => $message->appointment_id ? (string) $message->appointment_id : null,
        ]);
    }
}
