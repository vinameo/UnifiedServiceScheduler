<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

class TableRecordingChannel implements NotificationChannel
{
    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        $channel = $message->channel->value;
        $eventId = (string) $message->event_id;

        DB::table('notification_records')->insert([
            'event_id' => $eventId,
            'channel' => $channel,
            'created_at' => now(),
        ]);
    }
}
