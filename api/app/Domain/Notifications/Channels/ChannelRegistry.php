<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\OutboxChannel;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class ChannelRegistry implements ChannelRegistryInterface
{
    /**
     * @var array<string, NotificationChannel>
     */
    private array $channels = [];

    public function __construct(
        private readonly ?Container $container = null,
    ) {}

    public function register(OutboxChannel|string $channel, NotificationChannel $driver): void
    {
        $key = $channel instanceof OutboxChannel ? $channel->value : $channel;
        $this->channels[$key] = $driver;
    }

    public function channel(OutboxChannel|string $channel): NotificationChannel
    {
        $key = $channel instanceof OutboxChannel ? $channel->value : $channel;

        if (isset($this->channels[$key])) {
            return $this->channels[$key];
        }

        if ($this->container !== null) {
            $driver = $this->resolveConfiguredDriver($key);
            $this->channels[$key] = $driver;

            return $driver;
        }

        throw new InvalidArgumentException("No notification channel registered for [{$key}].");
    }

    public function get(OutboxChannel|string $channel): NotificationChannel
    {
        return $this->channel($channel);
    }

    private function resolveConfiguredDriver(string $channelKey): NotificationChannel
    {
        if ($this->container === null) {
            throw new InvalidArgumentException("Container is required to resolve driver for channel [{$channelKey}].");
        }

        $configKey = strtolower($channelKey);
        $driverName = (string) config("outbox.{$configKey}.driver", 'log');

        return match ($driverName) {
            'failing' => $this->container->make(FailingChannel::class),
            'log' => $this->container->make(LogChannel::class),
            'mail' => $this->container->make(MailChannel::class),
            default => throw new InvalidArgumentException("Unsupported driver [{$driverName}] for channel [{$channelKey}]."),
        };
    }
}
