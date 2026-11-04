<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Channels\DmsWebhookChannel;
use App\Domain\Notifications\Exceptions\DeliveryFailed;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DmsWebhookChannelTest extends TestCase
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createMessage(array $attributes = []): OutboxMessage
    {
        $message = new OutboxMessage;
        $message->id = (string) Str::uuid();
        $message->event_id = (string) Str::uuid();
        $message->dealership_id = (string) Str::uuid();
        $message->appointment_id = (string) Str::uuid();
        $message->event_type = OutboxEventType::AppointmentConfirmed;
        $message->channel = OutboxChannel::DMS;
        $message->payload = [
            'appointmentId' => $message->appointment_id,
            'reference' => 'APT-20261102-0001',
            'start' => '2026-11-02T09:00:00+07:00',
            'end' => '2026-11-02T10:00:00+07:00',
        ];
        $message->status = 'PENDING';
        $message->attempts = 0;
        $message->next_attempt_at = CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC');

        foreach ($attributes as $key => $value) {
            $message->{$key} = $value;
        }

        return $message;
    }

    private function createRecipient(): Recipient
    {
        return new Recipient(
            phone: '0901234567',
            email: 'customer@example.com',
            name: 'Nguyễn Văn Hùng',
            customerId: (string) Str::uuid(),
            userId: 42,
        );
    }

    public function test_send_posts_to_webhook_url_with_idempotency_key_and_event_id(): void
    {
        $url = 'https://dms.example.test/webhook';
        $message = $this->createMessage();
        $recipient = $this->createRecipient();

        Http::fake([
            $url => Http::response(['status' => 'acknowledged'], 200),
        ]);

        $channel = new DmsWebhookChannel($url);
        $channel->send($message, $recipient);

        Http::assertSent(function (Request $request) use ($url, $message) {
            $this->assertSame($url, $request->url());
            $this->assertTrue($request->hasHeader('Idempotency-Key', "{$message->event_id}:DMS"));

            $data = $request->data();
            $this->assertSame($message->event_id, $data['eventId']);
            $this->assertSame('AppointmentConfirmed', $data['eventType']);
            $this->assertSame($message->dealership_id, $data['dealershipId']);
            $this->assertSame($message->appointment_id, $data['appointmentId']);

            // INV-11.5: Body contains no phone or email
            $this->assertArrayNotHasKey('phone', $data);
            $this->assertArrayNotHasKey('email', $data);
            $rawBody = $request->body();
            $this->assertStringNotContainsString('0901234567', $rawBody);
            $this->assertStringNotContainsString('customer@example.com', $rawBody);

            return true;
        });
    }

    public function test_send_uses_configured_webhook_url_when_not_injected(): void
    {
        $url = 'https://config-dms.example.test/events';
        config(['outbox.dms.webhook_url' => $url]);

        $message = $this->createMessage();
        $recipient = $this->createRecipient();

        Http::fake([
            $url => Http::response(['status' => 'ok'], 200),
        ]);

        $channel = new DmsWebhookChannel;
        $channel->send($message, $recipient);

        Http::assertSent(function (Request $request) use ($url) {
            return $request->url() === $url;
        });
    }

    public function test_send_throws_delivery_failed_when_webhook_url_not_configured(): void
    {
        config(['outbox.dms.webhook_url' => '']);

        $message = $this->createMessage();
        $recipient = $this->createRecipient();

        $this->expectException(DeliveryFailed::class);
        $this->expectExceptionMessage('DMS webhook URL is not configured.');

        $channel = new DmsWebhookChannel;
        $channel->send($message, $recipient);
    }

    public function test_send_throws_delivery_failed_when_http_response_is_not_successful(): void
    {
        $url = 'https://dms.example.test/webhook';
        $message = $this->createMessage();
        $recipient = $this->createRecipient();

        Http::fake([
            $url => Http::response(['error' => 'Internal error'], 500),
        ]);

        $this->expectException(DeliveryFailed::class);
        $this->expectExceptionMessage('DMS webhook failed with status 500');

        $channel = new DmsWebhookChannel($url);
        $channel->send($message, $recipient);
    }

    public function test_send_throws_delivery_failed_on_network_or_connection_exception(): void
    {
        $url = 'https://dms.example.test/webhook';
        $message = $this->createMessage();
        $recipient = $this->createRecipient();

        Http::fake([
            $url => function () {
                throw new ConnectionException('Connection timed out after 5000 milliseconds');
            },
        ]);

        $this->expectException(DeliveryFailed::class);
        $this->expectExceptionMessage('DMS webhook delivery failed: Connection timed out');

        $channel = new DmsWebhookChannel($url);
        $channel->send($message, $recipient);
    }
}
