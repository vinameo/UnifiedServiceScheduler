<?php

namespace Tests\Unit\Booking;

use App\Domain\Booking\DayAvailability;
use App\Domain\Booking\Services\AlternativeTimesFinder;
use App\Domain\Booking\Services\AvailabilityCalculatorInterface;
use App\Domain\Booking\SlotState;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class AlternativeOrderTest extends TestCase
{
    private ServiceType $serviceType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->serviceType = new ServiceType([
            'id' => '11111111-1111-1111-1111-111111111111',
            'dealership_id' => 'dealership-1',
            'code' => 'OIL_CHANGE',
            'name' => 'Oil Change',
            'duration_minutes' => 60,
            'active' => true,
        ]);
    }

    /**
     * TC-06-09 (AC-06.9; Unit):
     * given Free slots 08:00, 10:00; wanted 09:00;
     * when near;
     * then [08:00, 10:00].
     */
    public function test_AC_06_9_ties_prefer_earlier(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        $day0Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:00:00', $tz),
                available: true,
                reason: null,
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 09:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 10:00:00', $tz),
                available: false,
                reason: 'NO_AVAILABILITY',
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 10:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 11:00:00', $tz),
                available: true,
                reason: null,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            function (string $dealershipId, ServiceType $type, string $localDate) use ($tz, $day0Slots): DayAvailability {
                if ($localDate === '2026-11-03') {
                    return $this->dayAvailability($localDate, $tz, $day0Slots);
                }

                // Days +1..+7 have no available slots
                return $this->dayAvailability($localDate, $tz, []);
            }
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
            limit: 3,
        );

        $this->assertCount(2, $results);

        // 08:00 and 10:00 are both 1 hour from 09:00. Tie goes to earlier first: 08:00 before 10:00.
        $this->assertSame('08:00', $results[0]->start->setTimezone($tz)->format('H:i'));
        $this->assertSame('10:00', $results[1]->start->setTimezone($tz)->format('H:i'));
    }

    public function test_wanted_time_itself_is_skipped(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        $day0Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:45:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:45:00', $tz),
                available: true,
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 09:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 10:00:00', $tz),
                available: true, // Even if marked available, wanted time must be skipped
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 09:15:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 10:15:00', $tz),
                available: true,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            fn (string $d, ServiceType $t, string $date) => $date === '2026-11-03'
                ? $this->dayAvailability($date, $tz, $day0Slots)
                : $this->dayAvailability($date, $tz, [])
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
        );

        $this->assertCount(2, $results);
        $starts = array_map(fn ($r) => $r->start->setTimezone($tz)->format('H:i'), $results);
        $this->assertNotContains('09:00', $starts);
        $this->assertSame(['08:45', '09:15'], $starts);
    }

    public function test_past_slots_are_skipped(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 08:30:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        $day0Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:15:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:15:00', $tz),
                available: true, // Past slot
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:45:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:45:00', $tz),
                available: true,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            fn (string $d, ServiceType $t, string $date) => $date === '2026-11-03'
                ? $this->dayAvailability($date, $tz, $day0Slots)
                : $this->dayAvailability($date, $tz, [])
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
        );

        $this->assertCount(1, $results);
        $this->assertSame('08:45', $results[0]->start->setTimezone($tz)->format('H:i'));
    }

    public function test_same_day_distance_ordering(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        $day0Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:00:00', $tz),
                available: true, // 60 min away
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 08:45:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 09:45:00', $tz),
                available: true, // 15 min away
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 10:30:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 11:30:00', $tz),
                available: true, // 90 min away
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturn($this->dayAvailability('2026-11-03', $tz, $day0Slots));

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
        );

        $this->assertCount(3, $results);
        $starts = array_map(fn ($r) => $r->start->setTimezone($tz)->format('H:i'), $results);
        $this->assertSame(['08:45', '08:00', '10:30'], $starts);
    }

    public function test_multi_day_fallback_in_time_order(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        // Day 0 only has 15:00 available
        $day0Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-03 15:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-03 16:00:00', $tz),
                available: true,
            ),
        ];

        // Day 1 has 08:00, 08:15, 08:30
        $day1Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-04 08:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-04 09:00:00', $tz),
                available: true,
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-04 08:15:00', $tz),
                end: CarbonImmutable::parse('2026-11-04 09:15:00', $tz),
                available: true,
            ),
            new SlotState(
                start: CarbonImmutable::parse('2026-11-04 08:30:00', $tz),
                end: CarbonImmutable::parse('2026-11-04 09:30:00', $tz),
                available: true,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            function (string $d, ServiceType $t, string $date) use ($tz, $day0Slots, $day1Slots): DayAvailability {
                return match ($date) {
                    '2026-11-03' => $this->dayAvailability($date, $tz, $day0Slots),
                    '2026-11-04' => $this->dayAvailability($date, $tz, $day1Slots),
                    default => $this->dayAvailability($date, $tz, []),
                };
            }
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
            limit: 3,
        );

        $this->assertCount(3, $results);
        $formatted = array_map(fn ($r) => $r->start->setTimezone($tz)->format('Y-m-d H:i'), $results);
        $this->assertSame([
            '2026-11-03 15:00',
            '2026-11-04 08:00',
            '2026-11-04 08:15',
        ], $formatted);
    }

    public function test_respects_90_day_horizon(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', $tz);
        // Horizon = 2026-11-02 + 90 days = 2027-01-31
        $wanted = CarbonImmutable::parse('2027-01-30 09:00:00', $tz); // 89 days ahead

        $day89Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2027-01-30 14:00:00', $tz),
                end: CarbonImmutable::parse('2027-01-30 15:00:00', $tz),
                available: true,
            ),
        ];

        $day90Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2027-01-31 08:00:00', $tz),
                end: CarbonImmutable::parse('2027-01-31 09:00:00', $tz),
                available: true,
            ),
        ];

        $day91Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2027-02-01 08:00:00', $tz), // past horizon!
                end: CarbonImmutable::parse('2027-02-01 09:00:00', $tz),
                available: true,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            function (string $d, ServiceType $t, string $date) use ($tz, $day89Slots, $day90Slots, $day91Slots): DayAvailability {
                return match ($date) {
                    '2027-01-30' => $this->dayAvailability($date, $tz, $day89Slots),
                    '2027-01-31' => $this->dayAvailability($date, $tz, $day90Slots),
                    '2027-02-01' => $this->dayAvailability($date, $tz, $day91Slots),
                    default => $this->dayAvailability($date, $tz, []),
                };
            }
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
            limit: 3,
        );

        // Only day 89 and day 90 slots are returned, never day 91
        $this->assertCount(2, $results);
        $dates = array_map(fn ($r) => $r->start->setTimezone($tz)->format('Y-m-d'), $results);
        $this->assertSame(['2027-01-30', '2027-01-31'], $dates);
    }

    public function test_skips_closed_days(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        // Day 0: no slots
        // Day 1: closed
        // Day 2: 08:00 available
        $day2Slots = [
            new SlotState(
                start: CarbonImmutable::parse('2026-11-05 08:00:00', $tz),
                end: CarbonImmutable::parse('2026-11-05 09:00:00', $tz),
                available: true,
            ),
        ];

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            function (string $d, ServiceType $t, string $date) use ($tz, $day2Slots): DayAvailability {
                return match ($date) {
                    '2026-11-03' => $this->dayAvailability($date, $tz, []),
                    '2026-11-04' => $this->closedDayAvailability($date, $tz),
                    '2026-11-05' => $this->dayAvailability($date, $tz, $day2Slots),
                    default => $this->dayAvailability($date, $tz, []),
                };
            }
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
            limit: 1,
        );

        $this->assertCount(1, $results);
        $this->assertSame('2026-11-05 08:00', $results[0]->start->setTimezone($tz)->format('Y-m-d H:i'));
    }

    public function test_returns_empty_when_all_8_days_are_full(): void
    {
        $tz = 'Asia/Ho_Chi_Minh';
        $now = CarbonImmutable::parse('2026-11-03 07:00:00', $tz);
        $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', $tz);

        $calculator = $this->createMock(AvailabilityCalculatorInterface::class);
        $calculator->method('forDay')->willReturnCallback(
            fn (string $d, ServiceType $t, string $date) => $this->dayAvailability($date, $tz, [])
        );

        $finder = new AlternativeTimesFinder($calculator);
        $results = $finder->near(
            dealershipId: 'dealership-1',
            type: $this->serviceType,
            wanted: $wanted,
            now: $now,
        );

        $this->assertSame([], $results);
    }

    /**
     * @param  list<SlotState>  $slots
     */
    private function dayAvailability(string $date, string $timezone, array $slots): DayAvailability
    {
        $freeCount = count(array_filter($slots, fn (SlotState $s) => $s->available));

        return new DayAvailability(
            date: $date,
            timezone: $timezone,
            closed: false,
            opensAt: '08:00',
            closesAt: '17:00',
            stepMinutes: 15,
            durationMinutes: 60,
            freeCount: $freeCount,
            slots: $slots,
        );
    }

    private function closedDayAvailability(string $date, string $timezone): DayAvailability
    {
        return new DayAvailability(
            date: $date,
            timezone: $timezone,
            closed: true,
            opensAt: null,
            closesAt: null,
            stepMinutes: 15,
            durationMinutes: 60,
            freeCount: 0,
            slots: [],
        );
    }
}
