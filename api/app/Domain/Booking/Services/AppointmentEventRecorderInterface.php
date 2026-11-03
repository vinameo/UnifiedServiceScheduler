<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;

interface AppointmentEventRecorderInterface
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function record(
        string $appointmentId,
        string $dealershipId,
        AppointmentEventType $type,
        int $version,
        CarbonImmutable $occurredAt,
        ?AppointmentStatus $fromStatus = null,
        ?AppointmentStatus $toStatus = null,
        ?int $actorUserId = null,
        ?string $actorRole = null,
        array $data = [],
    ): string;
}
