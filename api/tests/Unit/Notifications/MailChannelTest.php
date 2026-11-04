<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Channels\MailChannel;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\Mail\AppointmentNotification;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailChannelTest extends TestCase
{
    public function test_send_dispatches_appointment_notification_to_recipient_email(): void
    {
        Mail::fake();

        $message = new OutboxMessage([
            'id' => 'msg-mail-1',
            'event_id' => 'evt-mail-1',
            'dealership_id' => 'dlr-mail-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['reference' => 'APT-20261104-0042'],
        ]);

        $recipient = new Recipient(
            phone: '+84901234567',
            email: 'customer@example.com',
            name: 'Nguyễn Văn Customer',
        );

        $channel = new MailChannel;
        $channel->send($message, $recipient);

        Mail::assertSent(AppointmentNotification::class, function (AppointmentNotification $mail) use ($recipient) {
            return $mail->hasTo('customer@example.com')
                && $mail->recipient->email === $recipient->email
                && str_contains($mail->subjectLine, 'APT-20261104-0042');
        });
    }

    public function test_send_throws_delivery_failed_when_recipient_email_is_missing(): void
    {
        $message = new OutboxMessage([
            'id' => 'msg-mail-2',
            'event_id' => 'evt-mail-2',
            'dealership_id' => 'dlr-mail-1',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
        ]);

        $recipient = new Recipient(
            phone: '+84901234567',
            email: null,
        );

        $channel = new MailChannel;

        $this->expectException(DeliveryFailed::class);
        $this->expectExceptionMessage('Recipient has no email address.');

        $channel->send($message, $recipient);
    }
}
