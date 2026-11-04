<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;

class FailingChannel implements NotificationChannel
{
    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        // D-11.3 & AC-11.6: Always throws DeliveryFailed for demos and failure tests.
        throw new DeliveryFailed('Simulated channel delivery failure.');
    }
}
