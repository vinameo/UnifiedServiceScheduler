<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\OpeningHours;
use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class OpeningHoursTest extends TestCase
{
    /**
     * TC-03-20: AC-03.14
     * Given Seed opening hours (Mon-Fri 08:00–17:00, Sat 08:00–12:00, Sun closed in Asia/Ho_Chi_Minh);
     * when fits:
     *   - 16:00–17:00 Mon
     *   - 16:30–17:30 Mon
     *   - 09:00–10:00 Sun
     *   - 23:30–00:30 Mon->Tue
     * then true; false; false; false.
     */
    public function test_AC_03_14_range_must_fit_one_day_of_opening_hours(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        // Monday 2026-11-02 16:00–17:00 local (Asia/Ho_Chi_Minh is UTC+7) -> 09:00–10:00 UTC
        $mon16to17 = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 16:00:00', 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse('2026-11-02 17:00:00', 'Asia/Ho_Chi_Minh')
        );
        $this->assertTrue($openingHours->fits($mon16to17), '16:00–17:00 Mon fits opening hours 08:00–17:00.');

        // Monday 2026-11-02 16:30–17:30 local -> extends 30 minutes past closing
        $mon1630to1730 = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 16:30:00', 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse('2026-11-02 17:30:00', 'Asia/Ho_Chi_Minh')
        );
        $this->assertFalse($openingHours->fits($mon1630to1730), '16:30–17:30 Mon does not fit closing at 17:00.');

        // Sunday 2026-11-08 09:00–10:00 local -> Sunday is closed
        $sun09to10 = TimeRange::of(
            CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse('2026-11-08 10:00:00', 'Asia/Ho_Chi_Minh')
        );
        $this->assertFalse($openingHours->fits($sun09to10), 'Sunday is closed.');

        // 23:30–00:30 spanning across midnight (2026-11-02 23:30 to 2026-11-03 00:30 local)
        $midnightCrossing = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 23:30:00', 'Asia/Ho_Chi_Minh'),
            CarbonImmutable::parse('2026-11-03 00:30:00', 'Asia/Ho_Chi_Minh')
        );
        $this->assertFalse($openingHours->fits($midnightCrossing), 'Range crossing midnight must not fit (BR-02.2).');
    }

    /**
     * TC-03-25: Used by F02
     * Given Seed opening hours;
     * when latestStartFor('2026-11-02', 120); ('2026-11-07', 60); ('2026-11-08', 60);
     * then 15:00; 11:00; null.
     */
    public function test_latest_start(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        // Monday 2026-11-02: 08:00–17:00, duration 120 min -> latest start 15:00
        $monLatest = $openingHours->latestStartFor('2026-11-02', 120);
        $this->assertNotNull($monLatest);
        $this->assertSame('15:00', $monLatest->format('H:i'));
        $this->assertSame('2026-11-02 15:00', $monLatest->format('Y-m-d H:i'));
        $this->assertSame('Asia/Ho_Chi_Minh', $monLatest->timezone->getName());

        // Saturday 2026-11-07: 08:00–12:00, duration 60 min -> latest start 11:00
        $satLatest = $openingHours->latestStartFor('2026-11-07', 60);
        $this->assertNotNull($satLatest);
        $this->assertSame('11:00', $satLatest->format('H:i'));
        $this->assertSame('2026-11-07 11:00', $satLatest->format('Y-m-d H:i'));
        $this->assertSame('Asia/Ho_Chi_Minh', $satLatest->timezone->getName());

        // Sunday 2026-11-08: closed -> null
        $sunLatest = $openingHours->latestStartFor('2026-11-08', 60);
        $this->assertNull($sunLatest);
    }

    public function test_latest_start_returns_null_when_duration_exceeds_open_hours(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        // Saturday 08:00–12:00 has 240 minutes open; duration 300 minutes cannot fit
        $tooLong = $openingHours->latestStartFor('2026-11-07', 300);
        $this->assertNull($tooLong);
    }

    public function test_latest_start_exact_boundary(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        // Saturday has 240 minutes open; duration 240 minutes means latest start is opens_at (08:00)
        $exactFit = $openingHours->latestStartFor('2026-11-07', 240);
        $this->assertNotNull($exactFit);
        $this->assertSame('08:00', $exactFit->format('H:i'));
    }

    public function test_latest_start_refuses_non_positive_duration(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration minutes must be positive');
        $openingHours->latestStartFor('2026-11-02', 0);
    }

    public function test_latest_start_refuses_negative_duration(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duration minutes must be positive');
        $openingHours->latestStartFor('2026-11-02', -15);
    }

    public function test_windowFor_returns_utc_timerange(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        // Monday 2026-11-02: 08:00–17:00 UTC+7 -> 01:00–10:00 UTC
        $window = $openingHours->windowFor('2026-11-02');
        $this->assertNotNull($window);
        $this->assertSame('2026-11-02T01:00:00+00:00', $window->start->toIso8601String());
        $this->assertSame('2026-11-02T10:00:00+00:00', $window->end->toIso8601String());
        $this->assertSame(540, $window->minutes());
    }

    public function test_windowFor_returns_null_on_closed_day(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        $this->assertNull($openingHours->windowFor('2026-11-08'));
    }

    public function test_fromWeekdayMap_validates_weekday_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weekday must be between 1 (Monday) and 7 (Sunday), got 0.');

        OpeningHours::fromWeekdayMap([
            0 => ['opens_at' => '08:00:00', 'closes_at' => '17:00:00'],
        ], 'Asia/Ho_Chi_Minh');
    }

    public function test_fromWeekdayMap_validates_weekday_upper_bound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Weekday must be between 1 (Monday) and 7 (Sunday), got 8.');

        OpeningHours::fromWeekdayMap([
            8 => ['opens_at' => '08:00:00', 'closes_at' => '17:00:00'],
        ], 'Asia/Ho_Chi_Minh');
    }

    public function test_fromWeekdayMap_validates_closes_at_after_opens_at(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('closes_at (08:00:00) must be after opens_at (17:00:00)');

        OpeningHours::fromWeekdayMap([
            1 => ['opens_at' => '17:00:00', 'closes_at' => '08:00:00'],
        ], 'Asia/Ho_Chi_Minh');
    }

    public function test_fromWeekdayMap_validates_closes_at_equal_to_opens_at(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('closes_at (08:00:00) must be after opens_at (08:00:00)');

        OpeningHours::fromWeekdayMap([
            1 => ['opens_at' => '08:00:00', 'closes_at' => '08:00:00'],
        ], 'Asia/Ho_Chi_Minh');
    }

    public function test_fromWeekdayMap_accepts_objects(): void
    {
        $mon = (object) ['opens_at' => '08:00', 'closes_at' => '17:00'];
        $sat = (object) ['opensAt' => '09:00', 'closesAt' => '13:00'];

        $openingHours = OpeningHours::fromWeekdayMap([
            1 => $mon,
            6 => $sat,
        ], 'Europe/London');

        $monWindow = $openingHours->windowFor('2026-11-02');
        $this->assertNotNull($monWindow);
        $this->assertSame(540, $monWindow->minutes());

        $satWindow = $openingHours->windowFor('2026-11-07');
        $this->assertNotNull($satWindow);
        $this->assertSame(240, $satWindow->minutes());
    }

    public function test_fromWeekdayMap_validates_timezone(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid timezone 'Invalid/Timezone'.");

        OpeningHours::fromWeekdayMap([], 'Invalid/Timezone');
    }

    public function test_invalid_local_date_throws(): void
    {
        $openingHours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid local date: not-a-date.');

        $openingHours->windowFor('not-a-date');
    }
}
