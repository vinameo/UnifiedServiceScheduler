<?php

namespace App\Domain\Booking\Services;

use App\Domain\Booking\DayAvailability;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;

interface AvailabilityCalculatorInterface
{
    /**
     * Compute full day slot availability for a dealership on a given local date.
     */
    public function forDay(
        string $dealershipId,
        ServiceType $type,
        string $localDate,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
    ): DayAvailability;
}
