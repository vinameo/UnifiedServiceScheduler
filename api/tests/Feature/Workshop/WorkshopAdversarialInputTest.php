<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use App\Models\Appointment;
use App\Models\AppointmentEvent;
use App\Models\Dealership;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class WorkshopAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 1: Endpoint 12.5: minutes: 20, minutes: 0, minutes: "30", minutes: 600 -> 400 VALIDATION_ERROR
     */
    public function test_TC_12_23_row_1_extend_invalid_minutes_returns_400(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(9, 0),
            end: $now->setTime(10, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::IN_PROGRESS,
        );

        $invalidPayloads = [
            ['minutes' => 20],
            ['minutes' => 0],
            ['minutes' => '30'],
            ['minutes' => 600],
            ['minutes' => -15],
            ['minutes' => 30.5],
            [],
        ];

        foreach ($invalidPayloads as $payload) {
            $response = $this->actingAs($foreman)->postJson(
                "/api/v1/appointments/{$appointment->id}/extend",
                $payload,
                [
                    'If-Match' => sprintf('"%d"', $appointment->version),
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(400)
                ->assertJsonPath('code', 'VALIDATION_ERROR');
        }
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 2: Endpoint 12.5: Extend a CONFIRMED (not checked in) booking -> 422 INVALID_STATUS_TRANSITION
     */
    public function test_TC_12_23_row_2_extend_confirmed_booking_returns_422_invalid_status_transition(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(10, 0),
            end: $now->setTime(11, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CONFIRMED,
        );

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 3: Endpoint 12.5: Advisor extends -> 403 FORBIDDEN
     */
    public function test_TC_12_23_row_3_advisor_extends_returns_403(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(9, 0),
            end: $now->setTime(10, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::IN_PROGRESS,
        );

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/extend",
            ['minutes' => 30],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 4: Endpoint 12.1: Check in tomorrow's booking -> 422 NOT_SERVICE_DAY
     */
    public function test_TC_12_23_row_4_check_in_tomorrow_booking_returns_422_not_service_day(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $tomorrowStart = $now->addDay()->setTime(10, 0);
        $tomorrowEnd = $tomorrowStart->addHour();

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $tomorrowStart,
            end: $tomorrowEnd,
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CONFIRMED,
        );

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/check-in",
            [],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'NOT_SERVICE_DAY');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 5: Endpoint 12.4: No-show before start -> 422 NO_SHOW_BEFORE_START
     */
    public function test_TC_12_23_row_5_no_show_before_start_returns_422_no_show_before_start(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(10, 0),
            end: $now->setTime(11, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CONFIRMED,
        );

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/no-show",
            [],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'NO_SHOW_BEFORE_START');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 6: Endpoint 12.1: Check in a booking displaced by an overrun -> 422 NEEDS_RESCHEDULE
     */
    public function test_TC_12_23_row_6_check_in_displaced_booking_returns_422_needs_reschedule(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(10, 0),
            end: $now->setTime(11, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CONFIRMED,
        );

        /** @var AppointmentWriterInterface $writer */
        $writer = app(AppointmentWriterInterface::class);
        $writer->displace(
            appointmentId: $appointment->id,
            expectedVersion: 1,
            reason: 'OVERRUN',
            now: $now,
        );

        $fresh = Appointment::findOrFail($appointment->id);
        $this->assertSame(2, $fresh->version);
        $this->assertSame('OVERRUN', $fresh->contact_required_reason);

        $response = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/check-in",
            [],
            [
                'If-Match' => '"2"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'NEEDS_RESCHEDULE');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 7: Endpoint 12.3: Complete a CHECKED_IN (not started) booking -> 422 INVALID_STATUS_TRANSITION
     */
    public function test_TC_12_23_row_7_complete_checked_in_booking_returns_422_invalid_status_transition(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(9, 0),
            end: $now->setTime(10, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CHECKED_IN,
        );

        $response = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$appointment->id}/complete",
            [],
            [
                'If-Match' => sprintf('"%d"', $appointment->version),
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $response->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 8: Endpoint 12.10: date=2026-02-30 -> 400
     */
    public function test_TC_12_23_row_8_workshop_board_invalid_date_returns_400(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $response = $this->actingAs($foreman)->getJson(
            "/api/v1/dealerships/{$world->dealership->id}/workshop-board?date=2026-02-30"
        );

        $response->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 9: Endpoint 12.1–12.5: Booking of another dealership -> 404 APPOINTMENT_NOT_FOUND
     */
    public function test_TC_12_23_row_9_booking_of_another_dealership_returns_404_appointment_not_found(): void
    {
        $world1 = World::saigonD7();
        $foreman1 = $world1->foreman();

        $d2 = Dealership::factory()->create([
            'name' => 'Da Nang Central',
            'branch_name' => 'Hai Chau',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $world2 = World::forDealership($d2);

        $now = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $customer2 = $world2->customerWithVehicle();
        $tech2 = $world2->technician('An');
        $bay2 = $world2->bay('K1');
        $service2 = $world2->serviceType('OIL_CHANGE');

        $d2Appt = AppointmentFactory::create(
            dealership: $d2,
            technician: $tech2,
            bay: $bay2,
            start: $now,
            end: $now->addHour(),
            serviceType: $service2,
            customer: $customer2['customer'],
            vehicle: $customer2['vehicle'],
            status: AppointmentStatus::CONFIRMED,
        );

        $headerWithKey = fn () => [
            'If-Match' => sprintf('"%d"', $d2Appt->version),
            'Idempotency-Key' => (string) Str::uuid(),
        ];

        // 12.1 check-in
        $this->actingAs($foreman1)
            ->postJson("/api/v1/appointments/{$d2Appt->id}/check-in", [], $headerWithKey())
            ->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 12.2 start
        $this->actingAs($foreman1)
            ->postJson("/api/v1/appointments/{$d2Appt->id}/start", [], $headerWithKey())
            ->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 12.3 complete
        $this->actingAs($foreman1)
            ->postJson("/api/v1/appointments/{$d2Appt->id}/complete", [], $headerWithKey())
            ->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 12.4 no-show
        $this->actingAs($foreman1)
            ->postJson("/api/v1/appointments/{$d2Appt->id}/no-show", [], $headerWithKey())
            ->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');

        // 12.5 extend
        $this->actingAs($foreman1)
            ->postJson("/api/v1/appointments/{$d2Appt->id}/extend", ['minutes' => 30], $headerWithKey())
            ->assertStatus(404)
            ->assertJsonPath('code', 'APPOINTMENT_NOT_FOUND');
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 10: Endpoint 12.1: Same key twice -> The saved answer; one CHECKED_IN history row
     */
    public function test_TC_12_23_row_10_check_in_same_idempotency_key_twice_returns_saved_response_and_one_event(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(10, 0),
            end: $now->setTime(11, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::CONFIRMED,
        );

        $idempotencyKey = (string) Str::uuid();
        $headers = [
            'If-Match' => '"1"',
            'Idempotency-Key' => $idempotencyKey,
        ];

        // First attempt -> 200 CHECKED_IN
        $response1 = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/check-in",
            [],
            $headers
        );

        $response1->assertStatus(200)
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('status', 'CHECKED_IN')
            ->assertJsonPath('version', 2);

        // Second attempt with same key and body -> saved response
        $response2 = $this->actingAs($advisor)->postJson(
            "/api/v1/appointments/{$appointment->id}/check-in",
            [],
            $headers
        );

        $response2->assertStatus(200)
            ->assertHeader('ETag', '"2"')
            ->assertJsonPath('status', 'CHECKED_IN')
            ->assertJsonPath('version', 2);

        $this->assertSame($response1->json(), $response2->json());

        // Assert exactly one CHECKED_IN history event row exists
        $checkedInEvents = AppointmentEvent::query()
            ->where('appointment_id', $appointment->id)
            ->where('type', AppointmentEventType::CHECKED_IN->value)
            ->get();

        $this->assertCount(1, $checkedInEvents);
    }

    /**
     * TC-12-23 (API §B4; Integration):
     * Row 11: Endpoint 12.1–12.5: No If-Match -> 428 PRECONDITION_REQUIRED
     */
    public function test_TC_12_23_row_11_missing_if_match_returns_428(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(9, 0),
            end: $now->setTime(10, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::IN_PROGRESS,
        );

        $headerWithKey = fn () => ['Idempotency-Key' => (string) Str::uuid()];

        // 12.1 check-in without If-Match
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/check-in", [], $headerWithKey())
            ->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');

        // 12.2 start without If-Match
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/start", [], $headerWithKey())
            ->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');

        // 12.3 complete without If-Match
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/complete", [], $headerWithKey())
            ->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');

        // 12.4 no-show without If-Match
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/no-show", [], $headerWithKey())
            ->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');

        // 12.5 extend without If-Match
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/extend", ['minutes' => 30], $headerWithKey())
            ->assertStatus(428)
            ->assertJsonPath('code', 'PRECONDITION_REQUIRED');
    }

    /**
     * RQ §B8 / Bound test:
     * Missing Idempotency-Key on endpoints 12.1–12.5 returns 400 IDEMPOTENCY_KEY_REQUIRED
     */
    public function test_TC_12_23_missing_idempotency_key_returns_400(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();
        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        $appointment = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('An'),
            bay: $world->bay('K1'),
            start: $now->setTime(9, 0),
            end: $now->setTime(10, 0),
            serviceType: $world->serviceType('OIL_CHANGE'),
            status: AppointmentStatus::IN_PROGRESS,
        );

        $headers = [
            'If-Match' => sprintf('"%d"', $appointment->version),
        ];

        // 12.1 check-in
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/check-in", [], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        // 12.2 start
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/start", [], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        // 12.3 complete
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/complete", [], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        // 12.4 no-show
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/no-show", [], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        // 12.5 extend
        $this->actingAs($foreman)
            ->postJson("/api/v1/appointments/{$appointment->id}/extend", ['minutes' => 30], $headers)
            ->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');
    }
}
