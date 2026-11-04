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
}
