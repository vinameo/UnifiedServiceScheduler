<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class WorkshopScopeTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-12-17 (INV-12.10; Integration): given Foreman of D1; a D2 booking; D2;
     * when Check in, extend the D2 booking; open D2's board;
     * then 404 APPOINTMENT_NOT_FOUND twice; 404 DEALERSHIP_NOT_FOUND.
     */
    public function test_TC_12_17_scope_isolation(): void
    {
        $worldD1 = World::saigonD7();
        $foremanD1 = $worldD1->foreman();

        $d2 = Dealership::factory()->create([
            'name' => 'Hanoi Auto',
            'branch_name' => 'Cau Giay',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $worldD2 = World::forDealership($d2);

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customerD2 = $worldD2->customerWithVehicle();
        $techD2 = $worldD2->technician('An');
        $bayD2 = $worldD2->bay('K1');
        $serviceD2 = $worldD2->serviceType('OIL_CHANGE');

        $d2Appointment = AppointmentFactory::create(
            dealership: $d2,
            technician: $techD2,
            bay: $bayD2,
            start: CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh'),
            end: CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh'),
            serviceType: $serviceD2,
            customer: $customerD2['customer'],
            vehicle: $customerD2['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        // 1. Check in D2 booking as Foreman of D1 -> 404 APPOINTMENT_NOT_FOUND
        $responseCheckIn = $this->actingAs($foremanD1)->postJson(
            "/api/v1/appointments/{$d2Appointment->id}/check-in",
            [],
            [
                'If-Match' => sprintf('"%d"', $d2Appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $responseCheckIn->assertNotFound();
        $responseCheckIn->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 2. Extend D2 booking as Foreman of D1 -> 404 APPOINTMENT_NOT_FOUND
        $responseExtend = $this->actingAs($foremanD1)->postJson(
            "/api/v1/appointments/{$d2Appointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $d2Appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );
        $responseExtend->assertNotFound();
        $responseExtend->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 3. Open D2's workshop board as Foreman of D1 -> 404 DEALERSHIP_NOT_FOUND
        $responseBoard = $this->actingAs($foremanD1)->getJson(
            "/api/v1/dealerships/{$d2->id}/workshop-board?date=2026-11-02"
        );
        $responseBoard->assertNotFound();
        $responseBoard->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');
    }
}
