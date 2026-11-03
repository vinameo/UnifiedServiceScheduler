<?php

namespace App\Domain\Booking\Services;

use App\Domain\Allocation\AllocationRequest;
use App\Domain\Allocation\CandidateFinderInterface;
use App\Domain\Allocation\OpeningHours;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\BookingTimeRules;
use App\Domain\Booking\DayAvailability;
use App\Domain\Booking\Exceptions\StartInPast;
use App\Domain\Booking\Exceptions\StartTooFarAhead;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\SlotState;
use App\Domain\MasterData\Exceptions\DealershipNotFound;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;

final readonly class AvailabilityCalculator
{
    public function __construct(
        private MasterDataRepositoryInterface $masterDataRepository,
        private AllocationSnapshotRepositoryInterface $snapshotRepository,
        private CandidateFinderInterface $candidateFinder,
    ) {}

    public function forDay(
        string $dealershipId,
        ServiceType $type,
        string $localDate,
        CarbonImmutable $now,
        ?string $excludeAppointmentId = null,
    ): DayAvailability {
        $dealership = $this->masterDataRepository->findDealership($dealershipId);
        if ($dealership === null) {
            throw new DealershipNotFound;
        }

        $timezone = $dealership->timezone;
        $nowInTz = $now->setTimezone($timezone);
        $todayInTz = $nowInTz->toDateString();

        if ($localDate < $todayInTz) {
            throw new StartInPast;
        }

        $maxDateInTz = $nowInTz->addDays(BookingTimeRules::MAX_DAYS_AHEAD)->toDateString();
        if ($localDate > $maxDateInTz) {
            throw new StartTooFarAhead($maxDateInTz);
        }

        $openingHoursList = $this->masterDataRepository->openingHours($dealershipId);
        $openingHoursMap = [];
        foreach ($openingHoursList as $oh) {
            $openingHoursMap[$oh->weekday] = [
                'opens_at' => $oh->opens_at,
                'closes_at' => $oh->closes_at,
            ];
        }

        $openingHours = OpeningHours::fromWeekdayMap($openingHoursMap, $timezone);
        $date = CarbonImmutable::parse("{$localDate} 00:00:00", $timezone);
        $weekday = (int) $date->isoWeekday();

        if (! isset($openingHours->hours[$weekday])) {
            return new DayAvailability(
                date: $localDate,
                timezone: $timezone,
                closed: true,
                opensAt: null,
                closesAt: null,
                stepMinutes: BookingTimeRules::STEP_MINUTES,
                durationMinutes: $type->duration_minutes,
                freeCount: 0,
                slots: [],
            );
        }

        $opensAt = substr($openingHours->hours[$weekday]['opens_at'], 0, 5);
        $closesAt = substr($openingHours->hours[$weekday]['closes_at'], 0, 5);

        $openLocal = CarbonImmutable::parse("{$localDate} {$opensAt}", $timezone);
        $closeLocal = CarbonImmutable::parse("{$localDate} {$closesAt}", $timezone);
        $latestStart = $closeLocal->subMinutes($type->duration_minutes);

        if ($openLocal->greaterThan($latestStart)) {
            return new DayAvailability(
                date: $localDate,
                timezone: $timezone,
                closed: false,
                opensAt: $opensAt,
                closesAt: $closesAt,
                stepMinutes: BookingTimeRules::STEP_MINUTES,
                durationMinutes: $type->duration_minutes,
                freeCount: 0,
                slots: [],
            );
        }

        $snapshot = $this->snapshotRepository->forDay($dealershipId, $localDate, $now);

        $slots = [];
        $freeCount = 0;
        $currentStart = $openLocal;

        while ($currentStart->lessThanOrEqualTo($latestStart)) {
            $currentEnd = $currentStart->addMinutes($type->duration_minutes);

            if ($currentStart->lessThanOrEqualTo($now)) {
                $slots[] = new SlotState(
                    start: $currentStart,
                    end: $currentEnd,
                    available: false,
                    reason: 'PAST',
                );
            } else {
                $range = TimeRange::of(
                    $currentStart->setTimezone('UTC'),
                    $currentEnd->setTimezone('UTC'),
                );

                $request = new AllocationRequest(
                    dealershipId: $dealershipId,
                    requiredLevel: $type->requiredLevel(),
                    requiredBayType: $type->required_bay_type,
                    range: $range,
                    excludeAppointmentId: $excludeAppointmentId,
                );

                $result = $this->candidateFinder->find($request, $snapshot);

                if (! empty($result->pairs)) {
                    $slots[] = new SlotState(
                        start: $currentStart,
                        end: $currentEnd,
                        available: true,
                        reason: null,
                    );
                    $freeCount++;
                } else {
                    $slots[] = new SlotState(
                        start: $currentStart,
                        end: $currentEnd,
                        available: false,
                        reason: $result->failure !== null ? $result->failure->value : 'NO_AVAILABILITY',
                    );
                }
            }

            $currentStart = $currentStart->addMinutes(BookingTimeRules::STEP_MINUTES);
        }

        return new DayAvailability(
            date: $localDate,
            timezone: $timezone,
            closed: false,
            opensAt: $opensAt,
            closesAt: $closesAt,
            stepMinutes: BookingTimeRules::STEP_MINUTES,
            durationMinutes: $type->duration_minutes,
            freeCount: $freeCount,
            slots: $slots,
        );
    }
}
