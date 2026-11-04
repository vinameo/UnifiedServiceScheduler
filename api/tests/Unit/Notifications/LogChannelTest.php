<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Channels\LogChannel;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class LogChannelTest extends TestCase
{
    public function test_send_logs_metadata_without_personal_data(): void
    {
        $message = new OutboxMessage([
            'id' => 'msg-101',
            'event_id' => 'evt-101',
            'dealership_id' => 'dlr-202',
            'appointment_id' => 'apt-303',
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['reference' => 'APT-20261104-0001'],
        ]);

        $recipient = new Recipient(
            phone: '+84987654321',
            email: 'secret-customer@example.com',
            name: 'Nguyễn Văn Secret',
            customerId: 'cust-999',
        );

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $logMessage, array $context) use ($recipient) {
                // Must mention channel in log message
                $this->assertStringContainsString('SMS', $logMessage);

                // Context must contain metadata
                $this->assertSame('SMS', $context['channel']);
                $this->assertSame('evt-101', $context['eventId']);
                $this->assertSame('AppointmentConfirmed', $context['eventType']);
                $this->assertSame('dlr-202', $context['dealershipId']);
                $this->assertSame('apt-303', $context['appointmentId']);

                // Must NOT contain personal data (INV-11.5)
                $serialized = (string) json_encode(['msg' => $logMessage, 'context' => $context]);
                $this->assertNotNull($recipient->phone);
                $this->assertNotNull($recipient->email);
                $this->assertNotNull($recipient->name);
                $this->assertStringNotContainsString($recipient->phone, $serialized);
                $this->assertStringNotContainsString($recipient->email, $serialized);
                $this->assertStringNotContainsString($recipient->name, $serialized);

                return true;
            });

        $channel = new LogChannel;
        $channel->send($message, $recipient);
    }
}
