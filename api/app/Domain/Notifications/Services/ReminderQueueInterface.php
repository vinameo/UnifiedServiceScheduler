<?php

namespace App\Domain\Notifications\Services;

use Carbon\CarbonImmutable;

interface ReminderQueueInterface
{
    /**
     * D-11.6, INV-11.7: Queues appointment reminder SMS messages for confirmed bookings
     * with reminder_opt_in = true and start_at within [now + 23 h, now + 25 h).
     *
     * @return int Count of reminder messages successfully queued
     */
    public function queue(CarbonImmutable $now): int;
}
