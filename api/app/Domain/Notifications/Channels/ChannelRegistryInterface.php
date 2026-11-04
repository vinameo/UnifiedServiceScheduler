<?php

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\OutboxChannel;

interface ChannelRegistryInterface
{
    public function channel(OutboxChannel|string $channel): NotificationChannel;

    public function get(OutboxChannel|string $channel): NotificationChannel;

    public function register(OutboxChannel|string $channel, NotificationChannel $driver): void;
}
