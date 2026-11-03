<?php

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\OutboxEventType;
use Carbon\CarbonImmutable;

interface OutboxWriterInterface
{
    /**
     * @param  array<string, mixed>  $payload
     * @return string The event_id generated for this event across all channels
     */
    public function append(
        string $dealershipId,
        ?string $appointmentId,
        OutboxEventType $eventType,
        array $payload,
        CarbonImmutable $now,
    ): string;
}
