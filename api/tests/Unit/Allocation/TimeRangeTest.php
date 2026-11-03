<?php

namespace Tests\Unit\Allocation;

use App\Domain\Allocation\TimeRange;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TimeRangeTest extends TestCase
{
    /**
     * TC-03-11: INV-03.2, BR-03.1
     * Given Range A = 10:00–11:00;
     * when overlaps with: 09:00–10:00, 11:00–12:00, 10:15–10:45, 09:00–12:00, 10:59–11:30, 10:00–11:00;
     * then false, false, true, true, true, true.
     *
     * @return array<string, array{string, string, bool}>
     */
    public static function overlapCases(): array
    {
        return [
            'adjacent before' => ['09:00', '10:00', false],
            'adjacent after' => ['11:00', '12:00', false],
            'strictly inside' => ['10:15', '10:45', true],
            'enclosing' => ['09:00', '12:00', true],
            'overlap by 1 minute at end' => ['10:59', '11:30', true],
            'identical' => ['10:00', '11:00', true],
        ];
    }

    #[DataProvider('overlapCases')]
    public function test_INV_03_2_half_open_overlap(string $start, string $end, bool $expected): void
    {
        $rangeA = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
            CarbonImmutable::parse('2026-11-02 11:00:00Z'),
        );

        $rangeB = TimeRange::of(
            CarbonImmutable::parse("2026-11-02 {$start}:00Z"),
            CarbonImmutable::parse("2026-11-02 {$end}:00Z"),
        );

        $this->assertSame($expected, $rangeA->overlaps($rangeB), "Range A overlaps Range B ({$start}-{$end}) should be {$expected}");
        $this->assertSame($expected, $rangeB->overlaps($rangeA), "Range B ({$start}-{$end}) overlaps Range A should be symmetric");
    }

    public function test_INV_03_2_start_must_be_strictly_before_end(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TimeRange::of(
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
        );
    }

    public function test_INV_03_2_start_after_end_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TimeRange::of(
            CarbonImmutable::parse('2026-11-02 11:00:00Z'),
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
        );
    }

    public function test_R_03_1_normalises_timezones_to_utc(): void
    {
        $range = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 17:00:00+07:00'),
            CarbonImmutable::parse('2026-11-02 18:00:00+07:00'),
        );

        $this->assertSame('UTC', $range->start->timezoneName);
        $this->assertSame('UTC', $range->end->timezoneName);
        $this->assertSame('2026-11-02T10:00:00+00:00', $range->start->toIso8601String());
        $this->assertSame('2026-11-02T11:00:00+00:00', $range->end->toIso8601String());
    }

    public function test_contains_range_and_point(): void
    {
        $outer = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
            CarbonImmutable::parse('2026-11-02 12:00:00Z'),
        );

        $inner = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 10:30:00Z'),
            CarbonImmutable::parse('2026-11-02 11:30:00Z'),
        );

        $this->assertTrue($outer->contains($inner));
        $this->assertFalse($inner->contains($outer));

        $this->assertTrue($outer->contains(CarbonImmutable::parse('2026-11-02 10:00:00Z')));
        $this->assertTrue($outer->contains(CarbonImmutable::parse('2026-11-02 11:59:59Z')));
        $this->assertFalse($outer->contains(CarbonImmutable::parse('2026-11-02 12:00:00Z')));
        $this->assertFalse($outer->contains(CarbonImmutable::parse('2026-11-02 09:59:59Z')));
    }

    public function test_minutes_calculation(): void
    {
        $range = TimeRange::of(
            CarbonImmutable::parse('2026-11-02 10:00:00Z'),
            CarbonImmutable::parse('2026-11-02 11:30:00Z'),
        );

        $this->assertSame(90, $range->minutes());
    }
}
