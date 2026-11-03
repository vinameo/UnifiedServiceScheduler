<?php

namespace Tests\Feature\Booking\Availability;

use App\Models\Customer;
use App\Models\Dealership;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class AvailabilityAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    /**
     * API §B4 Row 1: Missing date / date=2026-11-31 / date=today -> 400 VALIDATION_ERROR
     */
    public function test_missing_or_invalid_date_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        // Missing date
        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // Invalid calendar date (November has 30 days)
        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-31"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // Non-format string 'today'
        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=today"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    /**
     * API §B4 Row 2: serviceTypeId=abc -> 400 VALIDATION_ERROR
     */
    public function test_service_type_id_not_uuid_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;

        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId=abc&date=2026-11-03"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    /**
     * API §B4 Row 3: serviceTypeId of another dealership -> 404 SERVICE_TYPE_NOT_FOUND
     */
    public function test_service_type_id_of_another_dealership_returns_404(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership1 = $world->dealership;

        $dealership2 = Dealership::factory()->create();
        $foreignServiceId = (string) Str::uuid();
        DB::table('service_types')->insert([
            'id' => $foreignServiceId,
            'dealership_id' => $dealership2->id,
            'code' => 'FOREIGN_SERVICE',
            'name' => 'Foreign Service',
            'duration_minutes' => 60,
            'required_level' => 1,
            'required_bay_type' => 'STANDARD',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership1->id}/availability?serviceTypeId={$foreignServiceId}&date=2026-11-03"
        )->assertStatus(404)
            ->assertJsonPath('code', 'SERVICE_TYPE_NOT_FOUND');
    }

    /**
     * API §B4 Row 4: date yesterday -> 422 START_IN_PAST
     */
    public function test_date_yesterday_returns_422_start_in_past(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-01"
            )->assertStatus(422)
                ->assertJsonPath('code', 'START_IN_PAST');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * API §B4 Row 5: date = today + 91 -> 422 START_TOO_FAR_AHEAD
     */
    public function test_date_91_days_ahead_returns_422_start_too_far_ahead(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // today (2026-11-02) + 91 days = 2027-02-01
            $date91 = $now->addDays(91)->toDateString();

            $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date={$date91}"
            )->assertStatus(422)
                ->assertJsonPath('code', 'START_TOO_FAR_AHEAD');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * API §B4 Row 6: excludeAppointmentId of another customer (called by customer) -> 404 APPOINTMENT_NOT_FOUND
     */
    public function test_exclude_appointment_id_of_another_customer_returns_404(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');
        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        ['user' => $customerUserA, 'customer' => $customerA, 'vehicle' => $vehicleA] = $world->customerWithVehicle();
        $customerB = Customer::factory()->create();
        $customerUserB = $customerB->user;

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointmentA = AppointmentFactory::create(
                dealership: $dealership,
                technician: $tech,
                bay: $bay,
                start: CarbonImmutable::parse('2026-11-03 10:00:00', 'Asia/Ho_Chi_Minh'),
                end: CarbonImmutable::parse('2026-11-03 11:00:00', 'Asia/Ho_Chi_Minh'),
                serviceType: $service,
                customer: $customerA,
                vehicle: $vehicleA,
            );

            // Customer B calls with appointment belonging to Customer A
            $this->actingAs($customerUserB)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-03&excludeAppointmentId={$appointmentA->id}"
            )->assertStatus(404)
                ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

            // Non-existent UUID also returns 404 APPOINTMENT_NOT_FOUND
            $randomUuid = (string) Str::uuid();
            $this->actingAs($customerUserA)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-03&excludeAppointmentId={$randomUuid}"
            )->assertStatus(404)
                ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * API §B4 Row 7: excludeAppointmentId=abc -> 400 VALIDATION_ERROR
     */
    public function test_exclude_appointment_id_not_uuid_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-03&excludeAppointmentId=abc"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    /**
     * API §B4 Row 8: after without an offset on /availability/next -> 400 VALIDATION_ERROR
     */
    public function test_after_without_offset_on_availability_next_returns_400(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $this->actingAs($advisor)->getJson(
            "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$service->id}&after=2026-11-02T09:00:00"
        )->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    /**
     * API §B4 Row 9: 121 requests in a minute on /availability -> 429 TOO_MANY_ATTEMPTS
     */
    public function test_availability_rate_limit_121_requests_in_a_minute_returns_429(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $user = User::factory()->customer()->create();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $uri = "/api/v1/dealerships/{$dealership->id}/availability?serviceTypeId={$service->id}&date=2026-11-03";

            // First 120 requests succeed within the rate limit
            for ($i = 1; $i <= 120; $i++) {
                $response = $this->actingAs($user)->getJson($uri);
                $response->assertOk();
            }

            // The 121st request triggers rate limiting (429 TOO_MANY_ATTEMPTS)
            $response121 = $this->actingAs($user)->getJson($uri);
            $response121->assertStatus(429)
                ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

            $retryAfter = (int) $response121->headers->get('Retry-After');
            $this->assertGreaterThan(0, $retryAfter);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * API §B4 Row 9: 121 requests in a minute on /availability/next -> 429 TOO_MANY_ATTEMPTS
     */
    public function test_availability_next_rate_limit_121_requests_in_a_minute_returns_429(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $service = $world->serviceType('OIL_CHANGE');

        $user = User::factory()->customer()->create();

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $uri = "/api/v1/dealerships/{$dealership->id}/availability/next?serviceTypeId={$service->id}&after=2026-11-02T09:00:00%2B07:00";

            // First 120 requests succeed within the rate limit
            for ($i = 1; $i <= 120; $i++) {
                $response = $this->actingAs($user)->getJson($uri);
                $response->assertOk();
            }

            // The 121st request triggers rate limiting (429 TOO_MANY_ATTEMPTS)
            $response121 = $this->actingAs($user)->getJson($uri);
            $response121->assertStatus(429)
                ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');

            $retryAfter = (int) $response121->headers->get('Retry-After');
            $this->assertGreaterThan(0, $retryAfter);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
