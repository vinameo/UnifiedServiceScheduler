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
use Illuminate\Support\Str;
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

    public function test_AC_10_9_booking_flow_frees_expired_holds(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $world->onlyBays(['K1']);

        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        ['user' => $customerAUser, 'customer' => $customerA] = $world->customerWithVehicle();
        ['user' => $customerBUser, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slotStart = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $slotEnd = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');

            // Customer A's expired hold (expired 5 minutes ago) holding An and K1 at 10:00
            $hold = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $slotStart,
                end: $slotEnd,
                customer: $customerA,
                status: AppointmentStatus::HELD,
                expiresAt: $now->subMinutes(5),
            );

            // Customer B books the 10:00 slot
            $response = $this->actingAs($customerBUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'vehicleId' => $vehicleB->id,
                    'desiredStart' => '2026-11-02T10:00:00+07:00',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(201);
            $newAppointmentId = $response->json('id');
            $this->assertNotNull($newAppointmentId);
            $this->assertNotSame($hold->id, $newAppointmentId);

            // The old hold is now EXPIRED with 0 active allocations
            $updatedHold = Appointment::findOrFail($hold->id);
            $this->assertSame(AppointmentStatus::EXPIRED, $updatedHold->status);

            $oldHoldActiveAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $hold->id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $oldHoldActiveAllocations);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow(null);
        }
    }

    public function test_hold_creation_frees_blocking_expired_holds(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $world->onlyBays(['K1']);

        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        ['user' => $customerAUser, 'customer' => $customerA] = $world->customerWithVehicle();
        ['user' => $customerBUser] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slotStart = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $slotEnd = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');

            // Customer A's expired hold (expired 5 minutes ago)
            $holdA = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $slotStart,
                end: $slotEnd,
                customer: $customerA,
                status: AppointmentStatus::HELD,
                expiresAt: $now->subMinutes(5),
            );

            // Customer B holds the 10:00 slot with mode HOLD
            $response = $this->actingAs($customerBUser)->postJson(
                '/api/v1/appointments',
                [
                    'dealershipId' => $world->dealership->id,
                    'serviceTypeId' => $service->id,
                    'desiredStart' => '2026-11-02T10:00:00+07:00',
                    'mode' => 'HOLD',
                ],
                ['Idempotency-Key' => (string) Str::uuid()]
            );

            $response->assertStatus(201);
            $response->assertJsonPath('status', AppointmentStatus::HELD->value);

            // Customer A's hold is now EXPIRED with 0 active allocations
            $updatedHoldA = Appointment::findOrFail($holdA->id);
            $this->assertSame(AppointmentStatus::EXPIRED, $updatedHoldA->status);
            $this->assertSame(0, DB::table('resource_allocations')->where('appointment_id', $holdA->id)->where('active', true)->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow(null);
        }
    }

    public function test_reschedule_frees_blocking_expired_holds(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $world->onlyBays(['K1']);

        $tech = $world->technician('An');
        $bay = $world->bay('K1');

        ['user' => $customerAUser, 'customer' => $customerA] = $world->customerWithVehicle();
        ['user' => $customerBUser, 'customer' => $customerB, 'vehicle' => $vehicleB] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot10 = CarbonImmutable::parse('2026-11-02 10:00:00', 'Asia/Ho_Chi_Minh');
            $slot11 = CarbonImmutable::parse('2026-11-02 11:00:00', 'Asia/Ho_Chi_Minh');
            $slot14 = CarbonImmutable::parse('2026-11-02 14:00:00', 'Asia/Ho_Chi_Minh');
            $slot15 = CarbonImmutable::parse('2026-11-02 15:00:00', 'Asia/Ho_Chi_Minh');

            // Customer A's expired hold at 10:00 (expired at 07:55)
            $holdA = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $slot10,
                end: $slot11,
                serviceType: $service,
                customer: $customerA,
                status: AppointmentStatus::HELD,
                expiresAt: $now->subMinutes(5),
            );

            // Customer B's existing confirmed appointment at 14:00
            $appointmentB = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $tech,
                bay: $bay,
                start: $slot14,
                end: $slot15,
                serviceType: $service,
                customer: $customerB,
                vehicle: $vehicleB,
                status: AppointmentStatus::CONFIRMED,
            );

            // Customer B reschedules their appointment to 10:00
            $response = $this->actingAs($customerBUser)->postJson(
                "/api/v1/appointments/{$appointmentB->id}/reschedule",
                [
                    'newStart' => '2026-11-02T10:00:00+07:00',
                ],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $response->assertStatus(200);

            // Old hold is now EXPIRED
            $updatedHoldA = Appointment::findOrFail($holdA->id);
            $this->assertSame(AppointmentStatus::EXPIRED, $updatedHoldA->status);
            $this->assertSame(0, DB::table('resource_allocations')->where('appointment_id', $holdA->id)->where('active', true)->count());

            // Customer B's appointment is now at 10:00
            $updatedB = Appointment::findOrFail($appointmentB->id);
            $this->assertSame(AppointmentStatus::CONFIRMED, $updatedB->status);
            $this->assertSame(2, $updatedB->version);
            $this->assertSame($slot10->setTimezone('UTC')->toIso8601String(), CarbonImmutable::parse($updatedB->start_at)->toIso8601String());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow(null);
        }
    }
}
