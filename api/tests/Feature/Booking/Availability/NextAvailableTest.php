<?php

namespace Tests\Feature\Booking\Availability;

use App\Models\Dealership;
use App\Models\ServiceType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class NextAvailableTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-06-12 (AC-06.12; Integration):
     * given Monday–Thursday full; Friday 08:00 free;
     * when availability/next?after=Monday 09:00;
     * then nextAvailable.start = Friday 08:00.
     */
    public function test_AC_06_12_next_free_slot(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE'); // 60 minutes

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');

        // Monday 2026-11-02 08:00 Asia/Ho_Chi_Minh
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // Monday (2026-11-02) full
        AppointmentFactory::busyAllDay($techAn, '2026-11-02');
        // Tuesday (2026-11-03) full
        AppointmentFactory::busyAllDay($techAn, '2026-11-03');
        // Wednesday (2026-11-04) full
        AppointmentFactory::busyAllDay($techAn, '2026-11-04');
        // Thursday (2026-11-05) full
        AppointmentFactory::busyAllDay($techAn, '2026-11-05');

        // Friday (2026-11-06) is completely free from 08:00

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertOk()
            ->assertJsonPath('data.nextAvailable.start', '2026-11-06T08:00:00+07:00')
            ->assertJsonPath('data.nextAvailable.end', '2026-11-06T09:00:00+07:00');
    }

    public function test_next_available_returns_same_day_slot_when_later_time_is_free(): void
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

        // An busy 08:00 - 10:00 on Monday
        AppointmentFactory::create(
            dealership: $dealership,
            technician: $techAn,
            bay: $bay,
            start: CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh'),
        );

        // When asking for after 09:00, 10:00 is the first free 60-minute slot
        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertOk()
            ->assertJsonPath('data.nextAvailable.start', '2026-11-02T10:00:00+07:00')
            ->assertJsonPath('data.nextAvailable.end', '2026-11-02T11:00:00+07:00');
    }

    public function test_next_available_returns_null_when_no_slots_within_7_days(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $world->onlyTechnicians(['An']);
        $techAn = $world->technician('An');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // Mark 8 consecutive days full (Monday through next Monday)
        for ($offset = 0; $offset <= 7; $offset++) {
            $day = $now->addDays($offset);
            if ((int) $day->isoWeekday() === 7) {
                continue; // Sunday is closed
            }
            AppointmentFactory::busyAllDay($techAn, $day->toDateString());
        }

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    'nextAvailable' => null,
                ],
            ]);
    }

    public function test_missing_after_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_after_without_timezone_offset_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        // Missing offset: 2026-11-02T09:00:00
        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T09:00:00"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_invalid_after_string_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=invalid-date"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_invalid_calendar_date_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        // 2026-02-30 does not exist
        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-02-30T09:00:00%2B07:00"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_missing_service_type_id_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_invalid_service_type_id_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;

        $response = $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId=not-a-uuid&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    public function test_foreign_dealership_advisor_returns_404_dealership_not_found(): void
    {
        $world = World::saigonD7();
        $dealership1 = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');

        $dealership2 = Dealership::factory()->create();
        $advisorD2 = User::factory()->advisor($dealership2)->create();

        $response = $this->actingAs($advisorD2)->getJson(
            "/api/v1/dealerships/{$dealership1->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertStatus(404)
            ->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');
    }

    public function test_inactive_service_type_returns_404_service_type_not_found(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;

        $inactiveService = ServiceType::create([
            'dealership_id' => $dealership->id,
            'code' => 'INACTIVE_FOR_NEXT',
            'name' => 'Inactive Service For Next',
            'description' => 'Inactive description',
            'duration_minutes' => 60,
            'required_level' => 1,
            'required_bay_type' => 'QUICK',
            'active' => false,
        ]);

        $customer = User::factory()->customer()->create();

        $response = $this->actingAs($customer)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$inactiveService->id}&after=2026-11-02T09:00:00%2B07:00"
        );

        $response->assertStatus(404)
            ->assertJsonPath('code', 'SERVICE_TYPE_NOT_FOUND');
    }

    public function test_customer_can_query_active_service_type(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $oilService = $world->serviceType('OIL_CHANGE');
        $customer = User::factory()->customer()->create();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $response = $this->actingAs($customer)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$oilService->id}&after=2026-11-02T08:00:00%2B07:00"
        );

        $response->assertOk()
            ->assertJsonPath('data.nextAvailable.start', '2026-11-02T08:15:00+07:00');
    }
}
