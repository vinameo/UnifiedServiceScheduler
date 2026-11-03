<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\OpeningHours;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class DayWindowTest extends TestCase
{
    /**
     * TC-03-26: INV-03.5
     * Given Dealership Europe/Berlin, opening hours 08:00–17:00, date 2026-10-25 (DST change);
     * when OpeningHours::windowFor('2026-10-25'); SnapshotBuilder day window;
     * then Opening window UTC 07:00–16:00; day window 2026-10-24T22:00Z–2026-10-25T23:00Z (25 hours).
     */
    public function test_INV_03_5_dst_day_uses_zone_rules(): void
    {
        // 2026-10-25 is a Sunday (weekday 7). Europe/Berlin turns clocks back (fall back).
        $openingHours = OpeningHours::fromWeekdayMap([
            7 => ['opens_at' => '08:00:00', 'closes_at' => '17:00:00'],
        ], 'Europe/Berlin');

        // Opening window UTC
        $openingWindow = $openingHours->windowFor('2026-10-25');
        $this->assertNotNull($openingWindow);
        $this->assertSame('2026-10-25T07:00:00+00:00', $openingWindow->start->toIso8601String());
        $this->assertSame('2026-10-25T16:00:00+00:00', $openingWindow->end->toIso8601String());
        $this->assertSame(540, $openingWindow->minutes());

        // SnapshotBuilder day window for 2026-10-25 in Europe/Berlin
        $dayWindow = SnapshotBuilder::dayWindow('2026-10-25', 'Europe/Berlin');
        $this->assertSame('2026-10-24T22:00:00+00:00', $dayWindow->start->toIso8601String());
        $this->assertSame('2026-10-25T23:00:00+00:00', $dayWindow->end->toIso8601String());
        $this->assertSame(1500, $dayWindow->minutes(), 'A 25-hour day across fall-back DST must have 1500 minutes.');
        $this->assertSame(25, (int) $dayWindow->start->diffInHours($dayWindow->end));
    }

    public function test_standard_day_without_dst_has_24_hours(): void
    {
        $dayWindow = SnapshotBuilder::dayWindow('2026-11-02', 'Asia/Ho_Chi_Minh');

        $this->assertSame('2026-11-01T17:00:00+00:00', $dayWindow->start->toIso8601String());
        $this->assertSame('2026-11-02T17:00:00+00:00', $dayWindow->end->toIso8601String());
        $this->assertSame(1440, $dayWindow->minutes());
        $this->assertSame(24, (int) $dayWindow->start->diffInHours($dayWindow->end));
    }

    public function test_spring_forward_dst_day_has_23_hours(): void
    {
        // 2026-03-29 in Europe/Berlin turns clocks forward 1 hour (02:00 -> 03:00)
        $dayWindow = SnapshotBuilder::dayWindow('2026-03-29', 'Europe/Berlin');

        $this->assertSame('2026-03-28T23:00:00+00:00', $dayWindow->start->toIso8601String());
        $this->assertSame('2026-03-29T22:00:00+00:00', $dayWindow->end->toIso8601String());
        $this->assertSame(1380, $dayWindow->minutes(), 'A 23-hour day across spring-forward DST must have 1380 minutes.');
        $this->assertSame(23, (int) $dayWindow->start->diffInHours($dayWindow->end));
    }

    public function test_dst_day_latest_start(): void
    {
        $openingHours = OpeningHours::fromWeekdayMap([
            7 => ['opens_at' => '08:00:00', 'closes_at' => '17:00:00'],
        ], 'Europe/Berlin');

        $latestStart = $openingHours->latestStartFor('2026-10-25', 120);
        $this->assertNotNull($latestStart);
        $this->assertSame('15:00', $latestStart->format('H:i'));
        $this->assertSame('Europe/Berlin', $latestStart->timezone->getName());
        $this->assertSame('2026-10-25T15:00:00+01:00', $latestStart->toIso8601String());
    }
}
