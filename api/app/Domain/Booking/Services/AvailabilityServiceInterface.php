<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\TimeRange;
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

    /**
     * Find up to $limit free slots near the wanted time (INV-06.5, D-06.4).
     *
     * @return list<TimeRange>
     */
    public function near(
        string $dealershipId,
        ServiceType|string $type,
        CarbonImmutable $wanted,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        int $limit = 3,
        ?User $actor = null,
    ): array;

    /**
     * Find the first free slot strictly after $after within 7 days and within the 90-day booking horizon (AC-06.12, D-06.7, INV-06.5).
     */
    public function next(
        string $dealershipId,
        ServiceType|string $type,
        CarbonImmutable $after,
        CarbonImmutable $now,
        ?User $actor = null,
    ): ?TimeRange;
}
