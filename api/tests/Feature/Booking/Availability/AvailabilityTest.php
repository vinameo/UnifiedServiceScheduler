<?php

namespace Tests\Feature\Booking\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AvailabilityTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_06_1_busy_technician_blocks_overlapping_starts(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $busyStart = CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh');
            $busyEnd = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');

            AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bay,
                start: $busyStart,
                end: $busyEnd,
            );

            $response = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );

            $response->assertOk()
                ->assertJsonPath('data.date', '2026-11-03')
                ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
                ->assertJsonPath('data.closed', false)
                ->assertJsonPath('data.opensAt', '08:00')
                ->assertJsonPath('data.closesAt', '17:00')
                ->assertJsonPath('data.stepMinutes', 15)
                ->assertJsonPath('data.durationMinutes', 60);

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots */
            $slots = $response->json('data.slots');
            $this->assertNotEmpty($slots);

            $slotsByTime = [];
            foreach ($slots as $slot) {
                $time = substr($slot['start'], 11, 5);
                $slotsByTime[$time] = $slot;
            }

            // 08:00 is free (08:00-09:00 does not overlap 09:00-10:00)
            $this->assertTrue($slotsByTime['08:00']['available']);
            $this->assertNull($slotsByTime['08:00']['reason']);

            // Overlapping starts: 08:15, 08:30, 08:45, 09:00, 09:15, 09:30, 09:45 must be false with NO_TECHNICIAN_AVAILABLE
            $blockedTimes = ['08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45'];
            foreach ($blockedTimes as $time) {
                $this->assertArrayHasKey($time, $slotsByTime, "Expected slot at {$time} to exist.");
                $this->assertFalse(
                    $slotsByTime[$time]['available'],
                    "Expected slot at {$time} to be false (busy technician overlaps)."
                );
                $this->assertSame(
                    'NO_TECHNICIAN_AVAILABLE',
                    $slotsByTime[$time]['reason'],
                    "Expected slot at {$time} reason to be NO_TECHNICIAN_AVAILABLE."
                );
            }

            // 10:00 is free (10:00-11:00 does not overlap 09:00-10:00)
            $this->assertTrue($slotsByTime['10:00']['available']);
            $this->assertNull($slotsByTime['10:00']['reason']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_06_2_last_start_is_close_minus_duration(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE'); // 60 minutes duration

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );

            $response->assertOk()
                ->assertJsonPath('data.date', '2026-11-03')
                ->assertJsonPath('data.closesAt', '17:00')
                ->assertJsonPath('data.durationMinutes', 60);

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots */
            $slots = $response->json('data.slots');
            $this->assertNotEmpty($slots);

            $lastSlot = $slots[count($slots) - 1];
            $this->assertSame('2026-11-03T16:00:00+07:00', $lastSlot['start']);
            $this->assertSame('2026-11-03T17:00:00+07:00', $lastSlot['end']);

            foreach ($slots as $slot) {
                $time = substr($slot['start'], 11, 5);
                $this->assertLessThanOrEqual(
                    '16:00',
                    $time,
                    "No slot should start after 16:00 (closing 17:00 minus 60 min duration), but found {$time}."
                );
            }
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
