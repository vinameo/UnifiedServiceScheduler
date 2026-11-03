<?php

namespace Tests\Unit\Booking;

use App\Domain\Booking\BookingTimeRules;
use App\Domain\Booking\Exceptions\OutsideOpeningHours;
use App\Domain\Booking\Exceptions\StartInPast;
use App\Domain\Booking\Exceptions\StartTooFarAhead;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Support\Allocation\SnapshotBuilder;

class BookingTimeRulesTest extends TestCase
{
    public function test_valid_start_and_duration_returns_utc_timerange(): void
    {
        $hours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $start = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');

        $range = BookingTimeRules::check($start, 60, $hours, $now);

        $this->assertSame('2026-11-02T02:00:00+00:00', $range->start->toIso8601String());
        $this->assertSame('2026-11-02T03:00:00+00:00', $range->end->toIso8601String());
        $this->assertSame(60, $range->minutes());
    }

    public function test_throws_start_in_past_when_start_is_before_or_equal_to_now(): void
    {
        $hours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');
        $start = CarbonImmutable::parse('2026-11-02 09:00:00', 'Asia/Ho_Chi_Minh');

        $this->expectException(StartInPast::class);
        BookingTimeRules::check($start, 60, $hours, $now);
    }

    public function test_throws_start_too_far_ahead_when_start_exceeds_90_days(): void
    {
        $hours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $start = CarbonImmutable::parse('2027-02-01 09:00:00', 'Asia/Ho_Chi_Minh');

        try {
            BookingTimeRules::check($start, 60, $hours, $now);
            $this->fail('Expected StartTooFarAhead exception');
        } catch (StartTooFarAhead $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('START_TOO_FAR_AHEAD', $e->problemCode);
            $this->assertSame('2027-01-31', $e->extra['maxDate']);
        }
    }

    public function test_throws_outside_opening_hours_when_extending_past_closing(): void
    {
        $hours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $start = CarbonImmutable::parse('2026-11-02 16:30:00', 'Asia/Ho_Chi_Minh');

        try {
            BookingTimeRules::check($start, 60, $hours, $now);
            $this->fail('Expected OutsideOpeningHours exception');
        } catch (OutsideOpeningHours $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('OUTSIDE_OPENING_HOURS', $e->problemCode);
            $this->assertSame('08:00', $e->extra['opensAt']);
            $this->assertSame('17:00', $e->extra['closesAt']);
            $this->assertSame('2026-11-02T16:00:00+07:00', $e->extra['latestStart']);
            $this->assertSame(60, $e->extra['durationMinutes']);
        }
    }

    public function test_throws_outside_opening_hours_when_closed_day(): void
    {
        $hours = SnapshotBuilder::seedOpeningHours('Asia/Ho_Chi_Minh');
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        $start = CarbonImmutable::parse('2026-11-08 09:00:00', 'Asia/Ho_Chi_Minh');

        try {
            BookingTimeRules::check($start, 60, $hours, $now);
            $this->fail('Expected OutsideOpeningHours exception');
        } catch (OutsideOpeningHours $e) {
            $this->assertSame(422, $e->status);
            $this->assertSame('OUTSIDE_OPENING_HOURS', $e->problemCode);
            $this->assertNull($e->extra['opensAt']);
            $this->assertNull($e->extra['closesAt']);
            $this->assertNull($e->extra['latestStart']);
        }
    }
}
