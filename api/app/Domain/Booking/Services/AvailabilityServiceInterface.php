<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\DayAvailability;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\CarbonImmutable;

interface AvailabilityServiceInterface
{
    /**
     * Compute availability for a dealership on a given local date.
     *
     * @return DayAvailability with list<SlotState{start, available, reason}>
     */
    public function forDay(
        string $dealershipId,
        ServiceType|string $type,
        string $localDate,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        ?User $actor = null,
    ): DayAvailability;
}
