<?php

namespace App\Domain\Notifications\Services;

use Carbon\CarbonImmutable;

interface OutboxRelayInterface
{
    /**
     * Claim and relay due pending outbox messages (INV-11.2–11.4).
     * Returns counts per outcome.
     */
    public function runOnce(CarbonImmutable $now, int $batch = 50): RelayResult;
}
