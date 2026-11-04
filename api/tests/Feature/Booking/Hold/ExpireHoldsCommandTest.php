<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentEventType;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Exceptions\AppointmentModified;
use App\Domain\Booking\Services\AppointmentTransitionInterface;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ExpireHoldsCommandTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_10_10_job_expires_only_expired_holds(): void
    {
        $world = World::saigonD7();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $techChi = $world->technician('Chi');
        $bay1 = $world->bay('K1');
        $bay2 = $world->bay('K2');
        $bay3 = $world->bay('K3');

        ['customer' => $c1] = $world->customerWithVehicle();
        ['customer' => $c2] = $world->customerWithVehicle();
        ['customer' => $c3] = $world->customerWithVehicle();
        ['customer' => $c4] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $this->travelTo($now);
        CarbonImmutable::setTestNow($now);

        try {
            // 3 expired holds:
            // Hold 1: expired 10 minutes ago
            $expiredHold1 = AppointmentFactory::hold(
                customer: $c1,
                start: $now->addHours(1),
                expiresAt: $now->subMinutes(10),
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bay1,
            );

            // Hold 2: expired 5 minutes ago
            $expiredHold2 = AppointmentFactory::hold(
                customer: $c2,
                start: $now->addHours(2),
                expiresAt: $now->subMinutes(5),
                dealership: $world->dealership,
                technician: $techDung,
                bay: $bay2,
            );

            // Hold 3: expired 1 second ago
            $expiredHold3 = AppointmentFactory::hold(
                customer: $c3,
                start: $now->addHours(3),
                expiresAt: $now->subSeconds(1),
                dealership: $world->dealership,
                technician: $techChi,
                bay: $bay3,
            );

            // 1 valid hold: expires 5 minutes in the future
            $validHold = AppointmentFactory::hold(
                customer: $c4,
                start: $now->addHours(4),
                expiresAt: $now->addMinutes(5),
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bay1,
            );

            // Verify initial state: all 4 are HELD and have active allocations
            $this->assertSame(AppointmentStatus::HELD, Appointment::findOrFail($expiredHold1->id)->status);
            $this->assertSame(AppointmentStatus::HELD, Appointment::findOrFail($expiredHold2->id)->status);
            $this->assertSame(AppointmentStatus::HELD, Appointment::findOrFail($expiredHold3->id)->status);
            $this->assertSame(AppointmentStatus::HELD, Appointment::findOrFail($validHold->id)->status);

            // Run artisan holds:expire
            $exitCode = Artisan::call('holds:expire');
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Expired 3 hold(s).', Artisan::output());

            // 1. Check the 3 expired holds: status EXPIRED, version 2, inactive allocations, history event
            foreach ([$expiredHold1, $expiredHold2, $expiredHold3] as $hold) {
                $refreshed = Appointment::findOrFail($hold->id);
                $this->assertSame(AppointmentStatus::EXPIRED, $refreshed->status);
                $this->assertSame(2, $refreshed->version);

                // Allocations deactivated
                $activeAllocations = DB::table('resource_allocations')
                    ->where('appointment_id', $hold->id)
                    ->where('active', true)
                    ->count();
                $this->assertSame(0, $activeAllocations);

                // History row with actor null and fromStatus HELD toStatus EXPIRED
                $events = DB::table('appointment_events')
                    ->where('appointment_id', $hold->id)
                    ->where('type', AppointmentEventType::EXPIRED->value)
                    ->get();

                $this->assertCount(1, $events);
                $event = $events->first();
                $this->assertNotNull($event);
                $this->assertSame('HELD', $event->from_status);
                $this->assertSame('EXPIRED', $event->to_status);
                $this->assertSame(2, (int) $event->version);
                $this->assertNull($event->actor_user_id);
                $this->assertNull($event->actor_role);
            }

            // 2. Check the valid hold: status still HELD, version 1, active allocations, no EXPIRED history event
            $refreshedValid = Appointment::findOrFail($validHold->id);
            $this->assertSame(AppointmentStatus::HELD, $refreshedValid->status);
            $this->assertSame(1, $refreshedValid->version);

            $validActiveAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $validHold->id)
                ->where('active', true)
                ->count();
            $this->assertGreaterThan(0, $validActiveAllocations);

            $validExpiredEvents = DB::table('appointment_events')
                ->where('appointment_id', $validHold->id)
                ->where('type', AppointmentEventType::EXPIRED->value)
                ->count();
            $this->assertSame(0, $validExpiredEvents);

            // 3. No outbox rows for any hold
            $outboxCount = DB::table('outbox_messages')
                ->whereIn('appointment_id', [$expiredHold1->id, $expiredHold2->id, $expiredHold3->id, $validHold->id])
                ->count();
            $this->assertSame(0, $outboxCount);

            // 4. Overlap assertions
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_job_accepts_now_option_in_testing_environment(): void
    {
        $world = World::saigonD7();
        ['customer' => $c1] = $world->customerWithVehicle();

        $appNow = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $this->travelTo($appNow);
        CarbonImmutable::setTestNow($appNow);

        try {
            // Hold expires at 10:05:00 UTC (in future relative to 10:00:00)
            $hold = AppointmentFactory::hold(
                customer: $c1,
                start: $appNow->addHours(2),
                expiresAt: $appNow->addMinutes(5),
                dealership: $world->dealership,
            );

            // Running without --now sees hold as still valid
            $exitCode1 = Artisan::call('holds:expire');
            $this->assertSame(0, $exitCode1);
            $this->assertStringContainsString('Expired 0 hold(s).', Artisan::output());

            $this->assertSame(AppointmentStatus::HELD, Appointment::findOrFail($hold->id)->status);

            // Running with --now set past expires_at (10:06:00 UTC) expires it
            $exitCode2 = Artisan::call('holds:expire', ['--now' => '2026-11-22T10:06:00Z']);
            $this->assertSame(0, $exitCode2);
            $this->assertStringContainsString('Expired 1 hold(s).', Artisan::output());

            $refreshedHold = Appointment::findOrFail($hold->id);
            $this->assertSame(AppointmentStatus::EXPIRED, $refreshedHold->status);
            $this->assertSame(2, $refreshedHold->version);
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    public function test_job_skips_concurrently_modified_hold_gracefully(): void
    {
        $world = World::saigonD7();
        ['customer' => $c1] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $this->travelTo($now);
        CarbonImmutable::setTestNow($now);

        try {
            $hold = AppointmentFactory::hold(
                customer: $c1,
                start: $now->addHours(1),
                expiresAt: $now->subMinute(),
                dealership: $world->dealership,
            );

            // Mock transition to throw AppointmentModified
            $mockTransition = $this->createMock(AppointmentTransitionInterface::class);
            $mockTransition->expects($this->once())
                ->method('transition')
                ->willThrowException(new AppointmentModified($hold->id, $hold));

            $this->app->instance(AppointmentTransitionInterface::class, $mockTransition);

            // Job should catch AppointmentModified, skip it, and succeed
            $exitCode = Artisan::call('holds:expire');
            $this->assertSame(0, $exitCode);
            $this->assertStringContainsString('Expired 0 hold(s).', Artisan::output());
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
