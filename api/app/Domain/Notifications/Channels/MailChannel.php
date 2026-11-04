<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\Mail\AppointmentNotification;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Mail;
use Throwable;

class MailChannel implements NotificationChannel
{
    public function send(OutboxMessage $message, Recipient $recipient): void
    {
        if (empty($recipient->email)) {
            throw new DeliveryFailed('Recipient has no email address.');
        }

        try {
            Mail::to($recipient->email)->send(new AppointmentNotification($message, $recipient));
        } catch (Throwable $e) {
            throw new DeliveryFailed(
                message: "Mail delivery failed: {$e->getMessage()}",
                code: (int) $e->getCode(),
                previous: $e,
            );
        }
    }
}
