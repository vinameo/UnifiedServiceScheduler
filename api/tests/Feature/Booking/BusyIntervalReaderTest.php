<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Domain\Booking\ResourceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class BusyIntervalReaderTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_04_21_expired_holds_are_not_busy(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $dealership = $world->dealership;

        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bay1 = $world->bay('K1');
        $bay2 = $world->bay('K2');
        $service = $world->serviceType('OIL_CHANGE');

        // Test day: 2026-11-04 (Wednesday). Opening hours: 08:00 - 17:00 Asia/Ho_Chi_Minh (01:00 - 10:00 UTC)
        // Frozen clock at 09:05 local (02:05 UTC)
        $now = CarbonImmutable::parse('2026-11-04 09:05:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

        // Appointment 1: An held 09:00 - 10:00 local (02:00 - 03:00 UTC), expires_at = now - 1s (expired!)
        $apt1Id = (string) Str::uuid();
        $apt1Start = CarbonImmutable::parse('2026-11-04 09:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');
        $apt1End = CarbonImmutable::parse('2026-11-04 10:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

        DB::table('appointments')->insert([
            'id' => $apt1Id,
            'reference' => 'APT-20261104-0001',
            'dealership_id' => $dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $techAn->id,
            'bay_id' => $bay1->id,
            'status' => AppointmentStatus::HELD->value,
            'start_at' => $apt1Start,
            'end_at' => $apt1End,
            'expires_at' => $now->subSecond(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'created_by_user_id' => $user->id,
            'created_at' => $now->subMinutes(5),
            'updated_at' => $now->subMinutes(5),
        ]);

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $apt1Id,
            'dealership_id' => $dealership->id,
            'resource_type' => ResourceType::TECHNICIAN->value,
            'resource_id' => $techAn->id,
            'start' => $apt1Start->toIso8601String(),
            'end' => $apt1End->toIso8601String(),
            'created_at' => $now->subMinutes(5)->toIso8601String(),
        ]);

        // Appointment 2: Dũng held 10:00 - 11:00 local (03:00 - 04:00 UTC), expires_at = now + 4m (valid hold!)
        ['user' => $user2, 'customer' => $customer2, 'vehicle' => $vehicle2] = $world->customerWithVehicle();
        $apt2Id = (string) Str::uuid();
        $apt2Start = CarbonImmutable::parse('2026-11-04 10:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');
        $apt2End = CarbonImmutable::parse('2026-11-04 11:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

        DB::table('appointments')->insert([
            'id' => $apt2Id,
            'reference' => 'APT-20261104-0002',
            'dealership_id' => $dealership->id,
            'customer_id' => $customer2->id,
            'vehicle_id' => $vehicle2->id,
            'service_type_id' => $service->id,
            'technician_id' => $techDung->id,
            'bay_id' => $bay2->id,
            'status' => AppointmentStatus::HELD->value,
            'start_at' => $apt2Start,
            'end_at' => $apt2End,
            'expires_at' => $now->addMinutes(4),
            'version' => 1,
            'source' => 'CUSTOMER',
            'created_by_user_id' => $user2->id,
            'created_at' => $now->subMinute(),
            'updated_at' => $now->subMinute(),
        ]);

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $apt2Id,
            'dealership_id' => $dealership->id,
            'resource_type' => ResourceType::TECHNICIAN->value,
            'resource_id' => $techDung->id,
            'start' => $apt2Start->toIso8601String(),
            'end' => $apt2End->toIso8601String(),
            'created_at' => $now->subMinute()->toIso8601String(),
        ]);

        /** @var AllocationSnapshotRepositoryInterface $snapshotRepo */
        $snapshotRepo = app(AllocationSnapshotRepositoryInterface::class);
        $snapshot = $snapshotRepo->forDay($dealership->id, '2026-11-04', $now);

        // Find technician An in snapshot: should NOT be busy (0 busy intervals)
        $anState = null;
        $dungState = null;
        foreach ($snapshot->technicians as $tech) {
            if ($tech->id === $techAn->id) {
                $anState = $tech;
            } elseif ($tech->id === $techDung->id) {
                $dungState = $tech;
            }
        }

        $this->assertNotNull($anState);
        $this->assertCount(0, $anState->busy, 'Expired hold should not make technician An busy');

        $this->assertNotNull($dungState);
        $this->assertCount(1, $dungState->busy, 'Active valid hold should make technician Dũng busy');
        $this->assertSame(
            $apt2Start->toIso8601String(),
            $dungState->busy[0]->range->start->toIso8601String()
        );
        $this->assertSame(
            $apt2End->toIso8601String(),
            $dungState->busy[0]->range->end->toIso8601String()
        );
    }
}
