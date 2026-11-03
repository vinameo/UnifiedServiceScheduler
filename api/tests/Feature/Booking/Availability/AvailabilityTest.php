<?php

namespace Tests\Feature\Booking\Availability;

use App\Domain\Booking\Services\AvailabilityServiceInterface;
use App\Models\Dealership;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
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

    public function test_AC_06_4_past_slots_are_not_available(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        // Clock frozen at 10:05 today (2026-11-02 10:05:00 Asia/Ho_Chi_Minh)
        $now = CarbonImmutable::parse('2026-11-02 10:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $response = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-02"
            );

            $response->assertOk()
                ->assertJsonPath('data.date', '2026-11-02')
                ->assertJsonPath('data.closed', false);

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots */
            $slots = $response->json('data.slots');
            $this->assertNotEmpty($slots);

            $slotsByTime = [];
            foreach ($slots as $slot) {
                $time = substr($slot['start'], 11, 5);
                $slotsByTime[$time] = $slot;
            }

            // Slots <= 10:00 must have available = false and reason = 'PAST'
            $pastTimes = ['08:00', '08:15', '08:30', '08:45', '09:00', '09:15', '09:30', '09:45', '10:00'];
            foreach ($pastTimes as $time) {
                $this->assertArrayHasKey($time, $slotsByTime, "Expected slot at {$time} to exist.");
                $this->assertFalse(
                    $slotsByTime[$time]['available'],
                    "Expected slot at {$time} to be false (in the past relative to 10:05)."
                );
                $this->assertSame(
                    'PAST',
                    $slotsByTime[$time]['reason'],
                    "Expected slot at {$time} reason to be PAST."
                );
            }

            // Slot 10:15 is in the future per F03 (nobody booked, so available = true, reason = null)
            $this->assertArrayHasKey('10:15', $slotsByTime, 'Expected slot at 10:15 to exist.');
            $this->assertTrue($slotsByTime['10:15']['available'], 'Expected slot at 10:15 to be available.');
            $this->assertNull($slotsByTime['10:15']['reason'], 'Expected slot at 10:15 reason to be null.');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_06_5_closed_day(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 2026-11-08 is Sunday (dealership is closed on Sundays)
            $response = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-08"
            );

            $response->assertOk()
                ->assertJsonPath('data.date', '2026-11-08')
                ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
                ->assertJsonPath('data.closed', true)
                ->assertJsonPath('data.opensAt', null)
                ->assertJsonPath('data.closesAt', null)
                ->assertJsonPath('data.freeCount', 0)
                ->assertJsonPath('data.slots', []);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_06_6_scope(): void
    {
        $world = World::saigonD7();
        $dealership1 = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 1. Advisor of D2 asks about D1 -> 404 DEALERSHIP_NOT_FOUND
            $responseForeign = $this->actingAs($advisorD2)->getJson(
                "/api/v1/dealerships/{$dealership1->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );
            $responseForeign->assertNotFound()
                ->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');

            // 2. Customer asks about an inactive service at D1 -> 404 SERVICE_TYPE_NOT_FOUND
            $customer = User::factory()->customer()->create();
            $inactiveService = ServiceType::create([
                'dealership_id' => $dealership1->id,
                'code' => 'INACTIVE_SERVICE',
                'name' => 'Inactive Service',
                'description' => 'Inactive service description',
                'duration_minutes' => 60,
                'required_level' => 1,
                'required_bay_type' => 'QUICK',
                'active' => false,
            ]);

            $responseInactive = $this->actingAs($customer)->getJson(
                "/api/v1/dealerships/{$dealership1->id}/availability?serviceTypeId={$inactiveService->id}&date=2026-11-03"
            );
            $responseInactive->assertNotFound()
                ->assertJsonPath('code', 'SERVICE_TYPE_NOT_FOUND');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_06_10_query_count_independent_of_slots(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $fakeLongService = ServiceType::create([
            'dealership_id' => $dealership->id,
            'code' => 'LONG_SERVICE',
            'name' => 'Long Service',
            'description' => 'Long service description',
            'duration_minutes' => 480,
            'required_level' => 1,
            'required_bay_type' => 'QUICK',
            'active' => true,
        ]);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // 1. Call for OIL_CHANGE and count queries
            DB::flushQueryLog();
            DB::enableQueryLog();

            $responseOil = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );
            $responseOil->assertOk();

            $queriesOil = count(DB::getQueryLog());
            DB::disableQueryLog();

            // 2. Call for fake long service and count queries
            DB::flushQueryLog();
            DB::enableQueryLog();

            $responseFake = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$fakeLongService->id}&date=2026-11-03"
            );
            $responseFake->assertOk();

            $queriesFake = count(DB::getQueryLog());
            DB::disableQueryLog();

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slotsOil */
            $slotsOil = $responseOil->json('data.slots');
            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slotsFake */
            $slotsFake = $responseFake->json('data.slots');

            $slotsOilCount = count($slotsOil);
            $slotsFakeCount = count($slotsFake);

            $this->assertNotEquals($slotsOilCount, $slotsFakeCount, 'Slot counts must differ between the two services.');
            $this->assertSame(
                $queriesOil,
                $queriesFake,
                "Query count must be independent of slots count ({$slotsOilCount} slots took {$queriesOil} queries, {$slotsFakeCount} slots took {$queriesFake} queries)."
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_list_is_a_hint_and_never_cached(): void
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
            // 1. Initial read: 10:00 is free
            $response1 = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );
            $response1->assertOk();

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots1 */
            $slots1 = $response1->json('data.slots');
            $slotsByTime1 = [];
            foreach ($slots1 as $slot) {
                $slotsByTime1[substr($slot['start'], 11, 5)] = $slot;
            }

            $this->assertTrue($slotsByTime1['10:00']['available'], '10:00 should initially be available.');
            $this->assertNull($slotsByTime1['10:00']['reason']);

            // 2. Another user books 10:00
            AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bay,
                start: CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh'),
                end: CarbonImmutable::parse('2026-11-03 11:00:00', 'Asia/Ho_Chi_Minh'),
            );

            // 3. Second read: 10:00 is immediately unavailable (no cache)
            $response2 = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$oilService->id}&date=2026-11-03"
            );
            $response2->assertOk();

            /** @var list<array{start: string, end: string, available: bool, reason: string|null}> $slots2 */
            $slots2 = $response2->json('data.slots');
            $slotsByTime2 = [];
            foreach ($slots2 as $slot) {
                $slotsByTime2[substr($slot['start'], 11, 5)] = $slot;
            }

            $this->assertFalse(
                $slotsByTime2['10:00']['available'],
                '10:00 must immediately show as unavailable on subsequent read.'
            );
            $this->assertSame('NO_TECHNICIAN_AVAILABLE', $slotsByTime2['10:00']['reason']);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_availability_service_near_integration(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Block An from 09:00 to 10:00 on 2026-11-03
            AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bay,
                start: CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh'),
                end: CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh'),
            );

            /** @var AvailabilityServiceInterface $service */
            $service = app(AvailabilityServiceInterface::class);

            $wanted = CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh');
            $alternatives = $service->near(
                dealershipId: $dealership->id,
                type: $oilService,
                wanted: $wanted,
                now: $now,
                limit: 3,
            );

            $this->assertNotEmpty($alternatives);
            // 08:00 is free and 10:00 is free (both 1 hr from 09:00). 08:00 must come before 10:00 (AC-06.9).
            $tz = $dealership->timezone;
            $this->assertSame('08:00', $alternatives[0]->start->setTimezone($tz)->format('H:i'));
            $this->assertSame('10:00', $alternatives[1]->start->setTimezone($tz)->format('H:i'));
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
