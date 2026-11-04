<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\AppointmentRepositoryInterface;
use App\Domain\Booking\Services\ExpiredHoldReleaserInterface;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ExpiredHoldReleaserTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_find_expired_holds_blocking_matches_only_overlapping_expired_holds(): void
    {
        $world = World::saigonD7();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $targetRange = TimeRange::of($now->addHour(), $now->addHours(2)); // 11:00 - 12:00

        // 1. Expired hold overlapping target range (11:00 - 12:00), expired 5 minutes ago
        $expiredOverlapping = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->addHour(),
            end: $now->addHours(2),
            status: AppointmentStatus::HELD,
            expiresAt: $now->subMinutes(5),
        );

        // 2. Active hold (not expired) overlapping target range (expires 5 minutes in future)
        $activeHold = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $world->technician('Dũng'),
            bay: $world->bay('K2'),
            start: $now->addHour(),
            end: $now->addHours(2),
            status: AppointmentStatus::HELD,
            expiresAt: $now->addMinutes(5),
        );

        // 3. Expired hold at a different time (08:00 - 09:00), non-overlapping
        $expiredNonOverlapping = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->subHours(2),
            end: $now->subHour(),
            status: AppointmentStatus::HELD,
            expiresAt: $now->subMinutes(30),
        );

        $repo = app(AppointmentRepositoryInterface::class);
        $found = $repo->findExpiredHoldsBlocking(
            dealershipId: $world->dealership->id,
            range: $targetRange,
            vehicleId: null,
            now: $now,
        );

        $this->assertCount(1, $found);
        $first = $found->first();
        $this->assertNotNull($first);
        $this->assertSame($expiredOverlapping->id, $first->id);
    }

    public function test_release_blocking_expires_holds_and_deactivates_allocations(): void
    {
        $world = World::saigonD7();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $targetRange = TimeRange::of($now->addHour(), $now->addHours(2));

        $hold = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->addHour(),
            end: $now->addHours(2),
            status: AppointmentStatus::HELD,
            expiresAt: $now->subMinutes(2),
        );

        $releaser = app(ExpiredHoldReleaserInterface::class);
        $count = $releaser->releaseBlocking(
            dealershipId: $world->dealership->id,
            range: $targetRange,
            vehicleId: null,
            now: $now,
        );

        $this->assertSame(1, $count);

        $updated = Appointment::findOrFail($hold->id);
        $this->assertSame(AppointmentStatus::EXPIRED, $updated->status);
        $this->assertSame(2, $updated->version);

        $activeAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $hold->id)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $activeAllocations);

        $this->assertNoOverlappingAllocations();

        // Second call should release 0
        $countSecond = $releaser->releaseBlocking(
            dealershipId: $world->dealership->id,
            range: $targetRange,
            vehicleId: null,
            now: $now,
        );
        $this->assertSame(0, $countSecond);
    }

    public function test_release_blocking_handles_vehicle_overlap(): void
    {
        $world = World::saigonD7();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-22 10:00:00', 'UTC');
        $targetRange = TimeRange::of($now->addHour(), $now->addHours(2));

        $hold = AppointmentFactory::create(
            dealership: $world->dealership,
            technician: $tech,
            bay: $bay,
            start: $now->addHour(),
            end: $now->addHours(2),
            customer: $customer,
            vehicle: $vehicle,
            status: AppointmentStatus::HELD,
            expiresAt: $now->subMinutes(1),
        );

        $releaser = app(ExpiredHoldReleaserInterface::class);
        $count = $releaser->releaseBlocking(
            dealershipId: $world->dealership->id,
            range: $targetRange,
            vehicleId: $vehicle->id,
            now: $now,
        );

        $this->assertSame(1, $count);
        $this->assertSame(AppointmentStatus::EXPIRED, Appointment::findOrFail($hold->id)->status);
    }
}
