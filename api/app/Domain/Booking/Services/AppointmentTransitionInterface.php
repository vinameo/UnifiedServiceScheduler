<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use App\Models\User;
use Carbon\CarbonImmutable;

interface AppointmentTransitionInterface
{
    /**
     * D-04.8, D-04.9, INV-04.5.
     *
     * @param  array<string, mixed>  $changes  extra columns (cancel_reason, checked_in_at...)
     */
    public function transition(
        string $id,
        int $expectedVersion,
        AppointmentStatus $to,
        array $changes,
        ?OutboxEventType $outbox,
        ?User $actor,
        CarbonImmutable $now,
    ): Appointment;
}
