<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AllocationRetried;
use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Booking\Exceptions\VehicleAlreadyBooked;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AppointmentRepository;
use App\Domain\Booking\Repositories\ResourceAllocationRepository;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\AppointmentWriter;
use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Level;
use App\Models\Bay;
use App\Models\Technician;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\World;
use Tests\TestCase;

class AppointmentWriterTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_04_9_all_or_nothing(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $startUtc = CarbonImmutable::parse('2026-11-04 02:00:00', 'UTC');
        $endUtc = $startUtc->addHour();
        $range = TimeRange::of($startUtc, $endUtc);

        $pair = new CandidatePair(
            new TechnicianState($tech->id, $tech->name, Level::from($tech->level)),
            new BayState($bay->id, $bay->code, $bay->bay_type),
        );

        $now = CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC');

        // Create failing allocation repository decorator that throws on vehicle allocation insert
        $realAllocationRepo = new ResourceAllocationRepository;
        $failingAllocationRepo = new class($realAllocationRepo) extends ResourceAllocationRepository
        {
            public function __construct(private ResourceAllocationRepository $inner) {}

            public function insertFor(string $appointmentId, string $dealershipId, array $allocations, CarbonImmutable $now): void
            {
                foreach ($allocations as $alloc) {
                    $type = $alloc['resource_type'] instanceof ResourceType ? $alloc['resource_type']->value : (string) $alloc['resource_type'];
                    if ($type === ResourceType::VEHICLE->value) {
                        throw new RuntimeException('Simulated vehicle allocation failure');
                    }
                }

                $this->inner->insertFor($appointmentId, $dealershipId, $allocations, $now);
            }
        };

        $writer = new AppointmentWriter(new AppointmentRepository, $failingAllocationRepo);

        $newAppointment = new NewAppointment(
            dealershipId: $world->dealership->id,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            serviceTypeId: $service->id,
            range: $range,
            createdByUserId: $user->id,
        );

        try {
            $writer->create($newAppointment, [$pair], $now);
            $this->fail('Expected RuntimeException on vehicle allocation insert');
        } catch (RuntimeException $e) {
            $this->assertSame('Simulated vehicle allocation failure', $e->getMessage());
        }

        // Entire transaction must have rolled back: 0 appointments, 0 allocations
        $this->assertSame(0, DB::table('appointments')->count(), 'No appointments must remain after failure');
        $this->assertSame(0, DB::table('resource_allocations')->count(), 'No allocations must remain after failure');
    }

    public function test_AC_04_8_vehicle_clash_fails_at_once(): void
    {
        Event::fake([AllocationRetried::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $bayK2 = $world->bay('K2');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-15 08:00:00', 'UTC');
        $t0900 = CarbonImmutable::parse('2026-11-15 09:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-15 10:00:00', 'UTC');
        $t0930 = CarbonImmutable::parse('2026-11-15 09:30:00', 'UTC');
        $t1030 = CarbonImmutable::parse('2026-11-15 10:30:00', 'UTC');

        $writer = new AppointmentWriter(new AppointmentRepository, new ResourceAllocationRepository);

        // First booking: v1 booked 09:00-10:00 with An + K1
        $firstAppointment = $writer->create(
            new NewAppointment(
                dealershipId: $world->dealership->id,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                serviceTypeId: $service->id,
                range: TimeRange::of($t0900, $t1000),
                createdByUserId: $user->id,
            ),
            [
                new CandidatePair(
                    new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                    new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
                ),
            ],
            $now,
        );

        $this->assertNotEmpty($firstAppointment->id);

        // Second booking attempt: same vehicle at 09:30 with Dung + K2 (which are free)
        // Even if multiple pairs are provided, vehicle clash fails at once
        $stalePairs = [
            new CandidatePair(
                new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
                new BayState($bayK2->id, $bayK2->code, $bayK2->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
            ),
        ];

        try {
            $writer->create(
                new NewAppointment(
                    dealershipId: $world->dealership->id,
                    customerId: $customer->id,
                    vehicleId: $vehicle->id,
                    serviceTypeId: $service->id,
                    range: TimeRange::of($t0930, $t1030),
                    createdByUserId: $user->id,
                ),
                $stalePairs,
                $now,
            );
            $this->fail('Expected VehicleAlreadyBooked exception');
        } catch (VehicleAlreadyBooked $e) {
            $problem = $e->toProblem();
            $this->assertSame(409, $problem->status);
            $this->assertSame('VEHICLE_ALREADY_BOOKED', $problem->code);
            $this->assertArrayHasKey('existingAppointment', $problem->extra);
            $this->assertSame($firstAppointment->id, $problem->extra['existingAppointment']['id']);

            // AllocationRetried must NOT be dispatched for vehicle clash
            Event::assertNotDispatched(AllocationRetried::class);
        }
    }

    public function test_AC_04_10_stale_pair_retries_next(): void
    {
        Event::fake([AllocationRetried::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $bayK2 = $world->bay('K2');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-16 08:00:00', 'UTC');
        $t0900 = CarbonImmutable::parse('2026-11-16 09:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-16 10:00:00', 'UTC');

        // An allocation for An 09:00-10:00 inserted by hand after the snapshot
        $otherVehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $dummyApptId = (string) Str::uuid();
        DB::table('appointments')->insert([
            'id' => $dummyApptId,
            'reference' => 'APT-20261116-9999',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $otherVehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $techAn->id,
            'bay_id' => $bayK1->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $t0900,
            'end_at' => $t1000,
            'version' => 1,
            'source' => 'STAFF',
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $dummyApptId,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::TECHNICIAN->value,
            'resource_id' => $techAn->id,
            'start' => $t0900->toIso8601String(),
            'end' => $t1000->toIso8601String(),
            'created_at' => $now->toIso8601String(),
        ]);

        $writer = new AppointmentWriter(new AppointmentRepository, new ResourceAllocationRepository);

        // Stale pairs: (An, K1), (Dung, K2)
        $stalePairs = [
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
                new BayState($bayK2->id, $bayK2->code, $bayK2->bay_type),
            ),
        ];

        $appointment = $writer->create(
            new NewAppointment(
                dealershipId: $world->dealership->id,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                serviceTypeId: $service->id,
                range: TimeRange::of($t0900, $t1000),
                createdByUserId: $user->id,
            ),
            $stalePairs,
            $now,
        );

        $this->assertSame($techDung->id, $appointment->technician_id);
        $this->assertSame($bayK2->id, $appointment->bay_id);

        // Exactly 1 AllocationRetried event dispatched for Technician An
        Event::assertDispatchedTimes(AllocationRetried::class, 1);
        Event::assertDispatched(function (AllocationRetried $event) use ($world, $techAn) {
            return $event->dealershipId === $world->dealership->id
                && $event->resourceType === ResourceType::TECHNICIAN
                && $event->resourceId === $techAn->id;
        });
    }

    public function test_AC_04_11_three_tries_then_slot_taken(): void
    {
        Event::fake([AllocationRetried::class]);

        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-17 08:00:00', 'UTC');
        $t0900 = CarbonImmutable::parse('2026-11-17 09:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-17 10:00:00', 'UTC');

        // Create 5 technicians, each with an overlapping allocation inserted by hand
        $otherVehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $techs = [];
        for ($i = 1; $i <= 5; $i++) {
            $tech = Technician::create([
                'dealership_id' => $world->dealership->id,
                'name' => "Tech Clash {$i}",
                'level' => 1,
                'active' => true,
            ]);
            $techs[] = $tech;

            $dummyApptId = (string) Str::uuid();
            DB::table('appointments')->insert([
                'id' => $dummyApptId,
                'reference' => "APT-20261117-00{$i}",
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $otherVehicle->id,
                'service_type_id' => $service->id,
                'technician_id' => $tech->id,
                'bay_id' => $bayK1->id,
                'status' => AppointmentStatus::CONFIRMED->value,
                'start_at' => $t0900,
                'end_at' => $t1000,
                'version' => 1,
                'source' => 'STAFF',
                'created_by_user_id' => $user->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::statement(<<<'SQL'
                INSERT INTO resource_allocations (
                    id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                ) VALUES (
                    :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
                )
            SQL, [
                'id' => (string) Str::uuid(),
                'appointment_id' => $dummyApptId,
                'dealership_id' => $world->dealership->id,
                'resource_type' => ResourceType::TECHNICIAN->value,
                'resource_id' => $tech->id,
                'start' => $t0900->toIso8601String(),
                'end' => $t1000->toIso8601String(),
                'created_at' => $now->toIso8601String(),
            ]);
        }

        $pairs = array_map(function (Technician $t) use ($bayK1): CandidatePair {
            return new CandidatePair(
                new TechnicianState($t->id, $t->name, Level::from($t->level)),
                new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
            );
        }, $techs);

        $writer = new AppointmentWriter(new AppointmentRepository, new ResourceAllocationRepository);

        try {
            $writer->create(
                new NewAppointment(
                    dealershipId: $world->dealership->id,
                    customerId: $customer->id,
                    vehicleId: $vehicle->id,
                    serviceTypeId: $service->id,
                    range: TimeRange::of($t0900, $t1000),
                    createdByUserId: $user->id,
                ),
                $pairs,
                $now,
            );
            $this->fail('Expected NoAvailability exception after 3 tries');
        } catch (NoAvailability $e) {
            $problem = $e->toProblem();
            $this->assertSame(409, $problem->status);
            $this->assertSame('NO_AVAILABILITY', $problem->code);
            $this->assertSame('SLOT_TAKEN', $problem->extra['reason'] ?? null);

            // Exactly 3 AllocationRetried events dispatched before stopping
            Event::assertDispatchedTimes(AllocationRetried::class, 3);
        }
    }

    public function test_D_04_16_conflict_on_a_technician_moves_to_the_next_one(): void
    {
        Event::fake([AllocationRetried::class]);

        $world = World::saigonD7();
        $world->onlyTechnicians(['Trần Minh An', 'Lý Hoàng Dũng']);
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $service = $world->serviceType('OIL_CHANGE');

        // Add 3 more QUICK bays so there are 5 QUICK bays total
        $bays = Bay::where('dealership_id', $world->dealership->id)
            ->where('bay_type', BayType::QUICK->value)
            ->get()
            ->all();

        while (count($bays) < 5) {
            $num = count($bays) + 1;
            $bay = Bay::create([
                'dealership_id' => $world->dealership->id,
                'code' => "Q{$num}",
                'name' => "Quick Bay {$num}",
                'bay_type' => BayType::QUICK,
                'active' => true,
            ]);
            $bays[] = $bay;
        }

        $now = CarbonImmutable::parse('2026-11-18 08:00:00', 'UTC');
        $t0900 = CarbonImmutable::parse('2026-11-18 09:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-18 10:00:00', 'UTC');

        // An's allocation 09:00-10:00 inserted by hand after the snapshot
        $otherVehicle = Vehicle::factory()->create(['customer_id' => $customer->id]);
        $dummyApptId = (string) Str::uuid();
        DB::table('appointments')->insert([
            'id' => $dummyApptId,
            'reference' => 'APT-20261118-0001',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $otherVehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $techAn->id,
            'bay_id' => $bays[0]->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $t0900,
            'end_at' => $t1000,
            'version' => 1,
            'source' => 'STAFF',
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::statement(<<<'SQL'
            INSERT INTO resource_allocations (
                id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
            ) VALUES (
                :id, :appointment_id, :dealership_id, :resource_type, :resource_id, tstzrange(:start, :end, '[)'), true, :created_at
            )
        SQL, [
            'id' => (string) Str::uuid(),
            'appointment_id' => $dummyApptId,
            'dealership_id' => $world->dealership->id,
            'resource_type' => ResourceType::TECHNICIAN->value,
            'resource_id' => $techAn->id,
            'start' => $t0900->toIso8601String(),
            'end' => $t1000->toIso8601String(),
            'created_at' => $now->toIso8601String(),
        ]);

        // Stale pairs: 5 pairs using An with different bays, and Dũng with bays
        $pairs = [
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bays[0]->id, $bays[0]->code, $bays[0]->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bays[1]->id, $bays[1]->code, $bays[1]->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bays[2]->id, $bays[2]->code, $bays[2]->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bays[3]->id, $bays[3]->code, $bays[3]->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
                new BayState($bays[4]->id, $bays[4]->code, $bays[4]->bay_type),
            ),
        ];

        $writer = new AppointmentWriter(new AppointmentRepository, new ResourceAllocationRepository);

        $appointment = $writer->create(
            new NewAppointment(
                dealershipId: $world->dealership->id,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                serviceTypeId: $service->id,
                range: TimeRange::of($t0900, $t1000),
                createdByUserId: $user->id,
            ),
            $pairs,
            $now,
        );

        // Conflict on An dropped all 4 pairs with An, and proceeded to Dũng
        $this->assertSame($techDung->id, $appointment->technician_id);
        $this->assertSame($bays[4]->id, $appointment->bay_id);

        // Exactly 1 retry event for An
        Event::assertDispatchedTimes(AllocationRetried::class, 1);
        Event::assertDispatched(function (AllocationRetried $event) use ($world, $techAn) {
            return $event->dealershipId === $world->dealership->id
                && $event->resourceType === ResourceType::TECHNICIAN
                && $event->resourceId === $techAn->id;
        });
    }
}
