<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;

interface NotificationChannel
{
    /**
     * Sends an outbox message to the resolved recipient.
     *
     * @throws DeliveryFailed
     */
    public function send(OutboxMessage $message, Recipient $recipient): void;
}
