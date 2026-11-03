<?php

namespace Tests\Feature\Booking\Availability;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AlternativesOnConflictTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-06-03 (AC-06.3, D-06.5; Integration):
     * given 09:00 full (An is the only one); 10:00, 11:00 free;
     * when POST /appointments 09:00;
     * then 409 NO_AVAILABILITY; alternatives[0].start = 10:00.
     */
    public function test_AC_06_3_conflict_returns_alternatives(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE'); // 60 minutes

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        $bay = $world->bay('K1');
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        // 08:30 now makes slots <= 08:30 past
        $now = CarbonImmutable::parse('2026-11-03 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // An busy 09:00-10:00 (also overlaps 08:45-09:45, 09:15-10:15, etc.)
        $busyStart = CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh');
        $busyEnd = CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh');
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: $busyStart,
            end: $busyEnd,
        );

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY')
            ->assertJsonPath('alternatives.0.start', '2026-11-03T10:00:00+07:00')
            ->assertJsonPath('alternatives.0.end', '2026-11-03T11:00:00+07:00');

        $this->assertNotEmpty($response->json('alternatives'));
        $this->assertLessThanOrEqual(3, count($response->json('alternatives')));
    }

    /**
     * TC-06-07 (AC-06.7; Integration):
     * given Today only 15:00 is free (wanted 09:00); tomorrow free;
     * when POST 09:00;
     * then alternatives = [15:00 today, 08:00 tomorrow, 08:15 tomorrow].
     */
    public function test_AC_06_7_same_day_first_then_next_days(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE'); // 60 minutes

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        $bay = $world->bay('K1');
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-03 07:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // Day hours: 08:00-17:00.
        // Busy 08:00-15:00 blocks all starts from 08:00 up to 14:45.
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-03 08:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-03 15:00:00', 'Asia/Ho_Chi_Minh'),
        );

        // Busy 16:00-17:00 blocks all starts after 15:00 (15:15-16:15, 15:30-16:30, 15:45-16:45, 16:00-17:00).
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-03 16:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-03 17:00:00', 'Asia/Ho_Chi_Minh'),
        );

        // Tomorrow (2026-11-04) is completely free from 08:00

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        $alternatives = $response->json('alternatives');
        $this->assertCount(3, $alternatives);
        $this->assertSame('2026-11-03T15:00:00+07:00', $alternatives[0]['start']);
        $this->assertSame('2026-11-04T08:00:00+07:00', $alternatives[1]['start']);
        $this->assertSame('2026-11-04T08:15:00+07:00', $alternatives[2]['start']);
    }

    /**
     * TC-06-08 (AC-06.8; Integration):
     * given 8 days full (one person, busy all day);
     * when POST;
     * then alternatives: [].
     */
    public function test_AC_06_8_no_alternatives_within_a_week(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 07:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // Mark 8 consecutive days full (today + 7 days)
        for ($offset = 0; $offset < 8; $offset++) {
            $day = $now->addDays($offset);
            if ((int) $day->isoWeekday() === 7) {
                // Sunday closed, no slots anyway
                continue;
            }
            AppointmentFactory::busyAllDay($techAn, $day->toDateString());
        }

        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2026-11-02T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY')
            ->assertJsonPath('alternatives', []);

        $this->assertSame([], $response->json('alternatives'));
    }

    /**
     * TC-06-19 (F04 + F06; Integration):
     * given POST 09:00 with K -> 409 with suggestions;
     * when Change data so suggestions would differ; resend K;
     * then The same 409 and the old alternatives.
     */
    public function test_replayed_409_keeps_saved_alternatives(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        $bay = $world->bay('K1');
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-03 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // An busy 09:00-10:00
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-03 09:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh'),
        );

        $key = (string) Str::uuid();

        // 1. Initial request returns 409 with suggestions (10:00, etc.)
        $response1 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
        ]);

        $response1->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        $originalAlternatives = $response1->json('alternatives');
        $this->assertNotEmpty($originalAlternatives);
        $this->assertSame('2026-11-03T10:00:00+07:00', $originalAlternatives[0]['start']);

        // 2. Change data so suggestions would differ if recomputed:
        // Book An 10:00-11:00 so 10:00 is no longer available!
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-03 11:00:00', 'Asia/Ho_Chi_Minh'),
        );

        // 3. Resend the exact same request with the same Idempotency-Key
        $response2 = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2026-11-03T09:00:00+07:00',
        ], [
            'Idempotency-Key' => $key,
            'Accept' => 'application/json',
        ]);

        // 4. Returns the exact same saved 409 response with old alternatives
        $response2->assertStatus(409)
            ->assertHeader('Idempotent-Replayed', 'true')
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        $this->assertSame($originalAlternatives, $response2->json('alternatives'));
    }

    /**
     * TC-06-21 (INV-06.5, D-06.4, D-06.7; Integration):
     * given Clock 2026-11-02; day 2027-01-29 (88 days ahead) full; 2027-02-01 free;
     * when POST for 2027-01-29 09:00; availability/next?after=2027-01-29T09:00+07:00;
     * then alternatives hold nothing after 2027-01-31; next is null or <= 2027-01-31.
     */
    public function test_INV_06_5_suggestions_stay_within_the_horizon(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // 2027-01-29 is 88 days ahead (Friday). Mark it full.
        AppointmentFactory::busyAllDay($techAn, '2027-01-29');

        // 2027-01-30 is 89 days ahead (Saturday). Mark it full as well.
        AppointmentFactory::busyAllDay($techAn, '2027-01-30');

        // 2027-01-31 is 90 days ahead (Sunday) - dealership is closed on Sundays.
        // 2027-02-01 is 91 days ahead (Monday) - completely free, but beyond the 90-day booking horizon.

        // 1. POST for 2027-01-29 09:00 -> 409 NO_AVAILABILITY
        $postResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $oilService->id,
            'desiredStart' => '2027-01-29T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
            'Accept' => 'application/json',
        ]);

        $postResponse->assertStatus(409)
            ->assertJsonPath('code', 'NO_AVAILABILITY');

        /** @var list<array{start: string, end: string}> $alternatives */
        $alternatives = $postResponse->json('alternatives');
        foreach ($alternatives as $alt) {
            $altDate = substr($alt['start'], 0, 10);
            $this->assertLessThanOrEqual(
                '2027-01-31',
                $altDate,
                "Alternative slot {$alt['start']} exceeds the 90-day horizon (2027-01-31)."
            );
        }

        // 2. GET availability/next?after=2027-01-29T09:00+07:00
        $nextResponse = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2027-01-29T09:00:00%2B07:00"
        );

        $nextResponse->assertOk();
        $nextAvailable = $nextResponse->json('data.nextAvailable');

        $this->assertTrue(
            $nextAvailable === null || substr((string) $nextAvailable['start'], 0, 10) <= '2027-01-31',
            'nextAvailable must be null or within the 90-day horizon (<= 2027-01-31).',
        );
    }
}
