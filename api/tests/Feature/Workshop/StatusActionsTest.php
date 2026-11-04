<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Models\AppointmentEvent;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class StatusActionsTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_12_1_check_in_start_complete(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $now->addMinutes(30),
                end: $now->addMinutes(90),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            $this->assertSame(1, $appointment->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $appointment->status);

            // 1. Advisor check-in ("1")
            $checkInNow = $now->addMinutes(5);
            CarbonImmutable::setTestNow($checkInNow);

            $response1 = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/check-in",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response1->assertStatus(200)
                ->assertHeader('ETag', '"2"')
                ->assertJsonPath('status', 'CHECKED_IN')
                ->assertJsonPath('version', 2);

            // 2. Foreman start ("2")
            $startNow = $now->addMinutes(30);
            CarbonImmutable::setTestNow($startNow);

            $response2 = $this->actingAs($foreman)->postJson(
                "/api/v1/appointments/{$appointment->id}/start",
                [],
                [
                    'If-Match' => '"2"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response2->assertStatus(200)
                ->assertHeader('ETag', '"3"')
                ->assertJsonPath('status', 'IN_PROGRESS')
                ->assertJsonPath('version', 3);

            // 3. Foreman complete ("3")
            $completeNow = $now->addMinutes(75);
            CarbonImmutable::setTestNow($completeNow);

            $response3 = $this->actingAs($foreman)->postJson(
                "/api/v1/appointments/{$appointment->id}/complete",
                [],
                [
                    'If-Match' => '"3"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response3->assertStatus(200)
                ->assertHeader('ETag', '"4"')
                ->assertJsonPath('status', 'COMPLETED')
                ->assertJsonPath('version', 4);

            // Assert status COMPLETED, checked_in_at, started_at, completed_at set
            $fresh = $appointment->fresh();
            $this->assertNotNull($fresh);
            $this->assertSame(AppointmentStatus::COMPLETED, $fresh->status);
            $this->assertNotNull($fresh->checked_in_at);
            $this->assertNotNull($fresh->started_at);
            $this->assertNotNull($fresh->completed_at);
            $this->assertTrue($checkInNow->equalTo($fresh->checked_in_at));
            $this->assertTrue($startNow->equalTo($fresh->started_at));
            $this->assertTrue($completeNow->equalTo($fresh->completed_at));

            // 0 active allocations
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointment->id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocations);

            // 3 history rows
            $events = AppointmentEvent::where('appointment_id', $appointment->id)
                ->orderBy('version')
                ->get();
            $this->assertCount(3, $events);
            /** @var AppointmentEvent $event0 */
            $event0 = $events[0];
            /** @var AppointmentEvent $event1 */
            $event1 = $events[1];
            /** @var AppointmentEvent $event2 */
            $event2 = $events[2];
            $this->assertSame(AppointmentEventType::CHECKED_IN->value, $event0->type);
            $this->assertSame(AppointmentEventType::STARTED->value, $event1->type);
            $this->assertSame(AppointmentEventType::COMPLETED->value, $event2->type);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_complete_requires_in_progress(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');

        // Case 1: CONFIRMED booking -> complete -> 422
        $confirmed = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->addMinutes(30),
            end: $now->addMinutes(90),
            serviceType: $service,
            status: AppointmentStatus::CONFIRMED,
        );

        $resConfirmed = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$confirmed->id}/complete",
            [],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $resConfirmed->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

        // Case 2: CHECKED_IN booking -> complete -> 422
        $tech2 = $world->technician('Dũng');
        $bay2 = $world->bay('K2');
        $checkedIn = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech2,
            bay: $bay2,
            start: $now->addMinutes(30),
            end: $now->addMinutes(90),
            serviceType: $service,
            status: AppointmentStatus::CHECKED_IN,
        );

        $resCheckedIn = $this->actingAs($foreman)->postJson(
            "/api/v1/appointments/{$checkedIn->id}/complete",
            [],
            [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]
        );

        $resCheckedIn->assertStatus(422)
            ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
    }

    public function test_AC_12_2_completed_cannot_check_in(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $completed = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $now->subHours(2),
                end: $now->subHour(),
                serviceType: $service,
                status: AppointmentStatus::COMPLETED,
            );

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$completed->id}/check-in",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(422)
                ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_12_5_check_in_only_on_service_day(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Tomorrow's booking
            $tomorrow = $now->addDay();
            $appointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $tomorrow->setTime(9, 0),
                end: $tomorrow->setTime(10, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            // Attempt check-in today -> 422 NOT_SERVICE_DAY
            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/check-in",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(422)
                ->assertJsonPath('code', 'NOT_SERVICE_DAY');

            // Travel to tomorrow (service day) -> check-in succeeds with 200
            $serviceDay = $tomorrow->setTime(8, 45);
            CarbonImmutable::setTestNow($serviceDay);

            $successResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/check-in",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $successResponse->assertStatus(200)
                ->assertHeader('ETag', '"2"')
                ->assertJsonPath('status', 'CHECKED_IN')
                ->assertJsonPath('version', 2);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_AC_12_6_no_show_only_after_start(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 10:50:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: CarbonImmutable::parse('2026-11-02 11:20:00', 'Asia/Ho_Chi_Minh'),
                end: CarbonImmutable::parse('2026-11-02 12:20:00', 'Asia/Ho_Chi_Minh'),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            // Active allocations initially exist
            $initialAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointment->id)
                ->where('active', true)
                ->count();
            $this->assertSame(3, $initialAllocations);

            // 1. Attempt no-show before start (now is 10:50, start is 11:20) -> 422 NO_SHOW_BEFORE_START
            $earlyResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/no-show",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $earlyResponse->assertStatus(422)
                ->assertJsonPath('code', 'NO_SHOW_BEFORE_START');

            $fresh = $appointment->fresh();
            $this->assertNotNull($fresh);
            $this->assertSame(AppointmentStatus::CONFIRMED, $fresh->status);
            $this->assertSame(1, $fresh->version);

            // 2. Travel to 11:20:00 (start time) -> no-show succeeds with 200
            $startTime = CarbonImmutable::parse('2026-11-02 11:20:00', 'Asia/Ho_Chi_Minh');
            CarbonImmutable::setTestNow($startTime);

            $successResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/no-show",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $successResponse->assertStatus(200)
                ->assertHeader('ETag', '"2"')
                ->assertJsonPath('status', 'NO_SHOW')
                ->assertJsonPath('version', 2);

            $updated = $appointment->fresh();
            $this->assertNotNull($updated);
            $this->assertSame(AppointmentStatus::NO_SHOW, $updated->status);
            $this->assertSame(2, $updated->version);

            // Active allocations become inactive (0 active)
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointment->id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocations);

            // Event recorded
            $events = AppointmentEvent::where('appointment_id', $appointment->id)
                ->orderBy('version')
                ->get();
            $this->assertCount(1, $events);
            /** @var AppointmentEvent $event */
            $event = $events[0];
            $this->assertSame(AppointmentEventType::NO_SHOW->value, $event->type);
            $this->assertSame(2, $event->version);
            $this->assertSame($advisor->id, $event->actor_user_id);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_foreman_can_mark_no_show(): void
    {
        $world = World::saigonD7();
        $foreman = $world->foreman();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $start = CarbonImmutable::parse('2026-11-02 11:20:00', 'Asia/Ho_Chi_Minh');
        $now = $start->addMinutes(10);
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $start,
                end: $start->addHour(),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            $response = $this->actingAs($foreman)->postJson(
                "/api/v1/appointments/{$appointment->id}/no-show",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(200)
                ->assertJsonPath('status', 'NO_SHOW');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_no_show_invalid_status_transition(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $start = CarbonImmutable::parse('2026-11-02 11:20:00', 'Asia/Ho_Chi_Minh');
        $now = $start->addMinutes(10);
        CarbonImmutable::setTestNow($now);

        try {
            $appointment = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $start,
                end: $start->addHour(),
                serviceType: $service,
                status: AppointmentStatus::COMPLETED,
            );

            $response = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointment->id}/no-show",
                [],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(422)
                ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
