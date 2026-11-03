<?php

namespace App\Domain\Booking;

use App\Domain\Allocation\OpeningHours;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\Exceptions\OutsideOpeningHours;
use App\Domain\Booking\Exceptions\StartInPast;
use App\Domain\Booking\Exceptions\StartTooFarAhead;
use Carbon\CarbonImmutable;

final readonly class BookingTimeRules
{
    public const int STEP_MINUTES = 15;

    public const int MAX_DAYS_AHEAD = 90;

    /**
     * Validates start time and opening hours, returning the UTC TimeRange.
     *
     * @throws StartInPast
     * @throws StartTooFarAhead
     * @throws OutsideOpeningHours
     */
    public static function check(
        CarbonImmutable $start,
        int $durationMinutes,
        OpeningHours $hours,
        CarbonImmutable $now,
    ): TimeRange {
        if ($start->lessThanOrEqualTo($now)) {
            throw new StartInPast;
        }

        $nowInTz = $now->setTimezone($hours->timezone);
        $maxDate = $nowInTz->addDays(self::MAX_DAYS_AHEAD)->toDateString();
        $startDateInTz = $start->setTimezone($hours->timezone)->toDateString();

        if ($startDateInTz > $maxDate) {
            throw new StartTooFarAhead($maxDate);
        }

        $end = $start->addMinutes($durationMinutes);
        $range = TimeRange::of($start->setTimezone('UTC'), $end->setTimezone('UTC'));

        if (! $hours->fits($range)) {
            $localStart = $start->setTimezone($hours->timezone);
            $localDate = $localStart->toDateString();
            $weekday = (int) $localStart->isoWeekday();

            $opensAt = null;
            $closesAt = null;
            if (isset($hours->hours[$weekday])) {
                $opensAt = substr($hours->hours[$weekday]['opens_at'], 0, 5);
                $closesAt = substr($hours->hours[$weekday]['closes_at'], 0, 5);
            }

            $latestStart = $hours->latestStartFor($localDate, $durationMinutes);

            throw new OutsideOpeningHours(
                opensAt: $opensAt,
                closesAt: $closesAt,
                latestStart: $latestStart?->toIso8601String(),
                durationMinutes: $durationMinutes,
            );
        }

        return $range;
    }
}
