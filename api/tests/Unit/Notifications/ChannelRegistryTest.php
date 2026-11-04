<?php

namespace Tests\Unit\Notifications;

use App\Domain\Notifications\Channels\ChannelRegistry;
use App\Domain\Notifications\Channels\DmsWebhookChannel;
use App\Domain\Notifications\Channels\FailingChannel;
use App\Domain\Notifications\Channels\LogChannel;
use App\Domain\Notifications\Channels\MailChannel;
use App\Domain\Notifications\OutboxChannel;
use InvalidArgumentException;
use Tests\TestCase;

class ChannelRegistryTest extends TestCase
{
    public function test_resolves_configured_drivers_from_container(): void
    {
        config([
            'outbox.sms.driver' => 'log',
            'outbox.email.driver' => 'mail',
            'outbox.dms.driver' => 'failing',
        ]);

        $registry = new ChannelRegistry($this->app);

        $this->assertInstanceOf(LogChannel::class, $registry->channel(OutboxChannel::SMS));
        $this->assertInstanceOf(MailChannel::class, $registry->channel(OutboxChannel::EMAIL));
        $this->assertInstanceOf(FailingChannel::class, $registry->channel(OutboxChannel::DMS));
    }

    public function test_resolves_webhook_driver_for_dms(): void
    {
        config([
            'outbox.dms.driver' => 'webhook',
            'outbox.dms.webhook_url' => 'https://dms.example.com',
        ]);

        $registry = new ChannelRegistry($this->app);

        $this->assertInstanceOf(DmsWebhookChannel::class, $registry->channel(OutboxChannel::DMS));
    }

    public function test_resolves_webhook_driver_when_webhook_url_set_with_default_log(): void
    {
        config([
            'outbox.dms.driver' => 'log',
            'outbox.dms.webhook_url' => 'https://dms.example.com',
        ]);

        $registry = new ChannelRegistry($this->app);

        $this->assertInstanceOf(DmsWebhookChannel::class, $registry->channel(OutboxChannel::DMS));
    }

    public function test_forget_clears_cached_driver(): void
    {
        $registry = new ChannelRegistry($this->app);
        $custom = new FailingChannel;

        $registry->register(OutboxChannel::SMS, $custom);
        $this->assertSame($custom, $registry->channel(OutboxChannel::SMS));

        $registry->forget(OutboxChannel::SMS);
        config(['outbox.sms.driver' => 'log']);
        $this->assertInstanceOf(LogChannel::class, $registry->channel(OutboxChannel::SMS));
    }

    public function test_manual_registration_overrides_configured_driver(): void
    {
        $registry = new ChannelRegistry($this->app);
        $custom = new FailingChannel;

        $registry->register(OutboxChannel::SMS, $custom);

        $this->assertSame($custom, $registry->channel(OutboxChannel::SMS));
        $this->assertSame($custom, $registry->get(OutboxChannel::SMS));
    }

    public function test_throws_exception_when_driver_unknown(): void
    {
        config(['outbox.sms.driver' => 'unsupported_provider']);

        $registry = new ChannelRegistry($this->app);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported driver [unsupported_provider] for channel [SMS].');

        $registry->channel(OutboxChannel::SMS);
    }

    public function test_throws_exception_without_container_and_unregistered(): void
    {
        $registry = new ChannelRegistry(null);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No notification channel registered for [SMS].');

        $registry->channel('SMS');
    }
}
