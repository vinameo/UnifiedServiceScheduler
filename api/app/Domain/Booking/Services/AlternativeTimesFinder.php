<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\BookingTimeRules;
use App\Domain\Booking\SlotState;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;

final readonly class AlternativeTimesFinder
{
    public function __construct(
        private AvailabilityCalculatorInterface $calculator,
        private ?MasterDataRepositoryInterface $masterDataRepository = null,
    ) {}

    /**
     * Find up to $limit free slots near the wanted time (INV-06.5, D-06.4).
     *
     * Ordering:
     * - Free slots on the same day by absolute distance from wanted start time (|t - wanted|).
     * - Ties are broken earlier first (AC-06.9).
     * - Skips past times (t <= now) and skips the wanted time itself.
     * - If fewer than $limit, checks days +1 to +7 in chronological order (earliest first).
     * - Never suggests slots past the 90-day booking horizon (BookingTimeRules::MAX_DAYS_AHEAD).
     *
     * @return list<TimeRange>
     */
    public function near(
        string $dealershipId,
        ServiceType $type,
        CarbonImmutable $wanted,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
        int $limit = 3,
    ): array {
        if ($limit <= 0) {
            return [];
        }

        $dealership = $this->masterDataRepository?->findDealership($dealershipId);
        $timezone = $dealership !== null ? $dealership->timezone : $wanted->getTimezone()->getName();

        $wantedInTz = $wanted->setTimezone($timezone);
        $wantedDate = $wantedInTz->toDateString();
        $nowInTz = $now->setTimezone($timezone);
        $maxDateInTz = $nowInTz->addDays(BookingTimeRules::MAX_DAYS_AHEAD)->toDateString();

        if ($wantedDate > $maxDateInTz) {
            return [];
        }

        $sameDayAvailability = $this->calculator->forDay(
            dealershipId: $dealershipId,
            type: $type,
            localDate: $wantedDate,
            now: $now,
            excludeAppointmentId: $excludeAppointmentId,
        );

        /** @var list<SlotState> $sameDayFree */
        $sameDayFree = [];
        if (! $sameDayAvailability->closed) {
            foreach ($sameDayAvailability->slots as $slot) {
                if (! $slot->available) {
                    continue;
                }

                // Skips past times
                if ($slot->start->lessThanOrEqualTo($now)) {
                    continue;
                }

                // Skips the wanted time itself
                if ($slot->start->getTimestamp() === $wanted->getTimestamp()) {
                    continue;
                }

                $sameDayFree[] = $slot;
            }
        }

        usort($sameDayFree, function (SlotState $a, SlotState $b) use ($wanted): int {
            $distA = abs($a->start->getTimestamp() - $wanted->getTimestamp());
            $distB = abs($b->start->getTimestamp() - $wanted->getTimestamp());

            if ($distA !== $distB) {
                return $distA <=> $distB;
            }

            // Ties: earlier first (AC-06.9)
            return $a->start->getTimestamp() <=> $b->start->getTimestamp();
        });

        /** @var list<SlotState> $collected */
        $collected = array_slice($sameDayFree, 0, $limit);

        if (count($collected) < $limit) {
            for ($dayOffset = 1; $dayOffset <= 7; $dayOffset++) {
                $nextDateInTz = $wantedInTz->addDays($dayOffset);
                $nextLocalDate = $nextDateInTz->toDateString();

                // Never suggests slots past the 90-day booking horizon (BookingTimeRules::MAX_DAYS_AHEAD)
                if ($nextLocalDate > $maxDateInTz) {
                    break;
                }

                $nextDayAvailability = $this->calculator->forDay(
                    dealershipId: $dealershipId,
                    type: $type,
                    localDate: $nextLocalDate,
                    now: $now,
                    excludeAppointmentId: $excludeAppointmentId,
                );

                if ($nextDayAvailability->closed) {
                    continue;
                }

                foreach ($nextDayAvailability->slots as $slot) {
                    if ($slot->available && $slot->start->greaterThan($now)) {
                        $collected[] = $slot;
                        if (count($collected) >= $limit) {
                            break 2;
                        }
                    }
                }
            }
        }

        $results = [];
        foreach ($collected as $slot) {
            $results[] = TimeRange::of($slot->start, $slot->end);
        }

        return $results;
    }
}
