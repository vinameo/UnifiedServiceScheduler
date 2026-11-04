<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Channels\FailingChannel;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use PHPUnit\Framework\TestCase;

class FailingChannelTest extends TestCase
{
    public function test_send_always_throws_delivery_failed(): void
    {
        $message = new OutboxMessage([
            'id' => 'msg-fail',
            'event_id' => 'evt-fail',
            'dealership_id' => 'dlr-fail',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
        ]);

        $recipient = new Recipient(
            phone: '+84901234567',
            email: 'user@example.com',
        );

        $channel = new FailingChannel;

        $this->expectException(DeliveryFailed::class);
        $this->expectExceptionMessage('Simulated channel delivery failure.');

        $channel->send($message, $recipient);
    }
}
