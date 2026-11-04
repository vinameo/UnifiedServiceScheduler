<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentEventType;
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
use App\Domain\Notifications\OutboxEventType;
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
            $this->assertSame('CONFIRMED', $problem->extra['existingAppointment']['status']);

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

    public function test_INV_04_15_disabled_technician_is_never_allocated(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-19 08:00:00', 'UTC');
        $t0900 = CarbonImmutable::parse('2026-11-19 09:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-19 10:00:00', 'UTC');

        // Pairs from snapshot: (An, K1), (Dung, K1)
        $pairs = [
            new CandidatePair(
                new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
                new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
            ),
            new CandidatePair(
                new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
                new BayState($bayK1->id, $bayK1->code, $bayK1->bay_type),
            ),
        ];

        // An is disabled after the snapshot was taken
        DB::table('technicians')->where('id', $techAn->id)->update(['active' => false]);

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

        // Appointment allocated to Dung
        $this->assertSame($techDung->id, $appointment->technician_id);

        // An must have 0 allocations in resource_allocations
        $anAllocationsCount = DB::table('resource_allocations')
            ->where('resource_type', 'TECHNICIAN')
            ->where('resource_id', $techAn->id)
            ->count();
        $this->assertSame(0, $anAllocationsCount, 'Disabled technician must never receive allocations');
    }

    public function test_INV_04_7_move_frees_old_before_taking_new(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $techAn = $world->technician('An');
        $techDung = $world->technician('Dũng');
        $bayK2 = $world->bay('K2');
        $bayK3 = $world->bay('K3');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-20 08:00:00', 'UTC');
        $t1000 = CarbonImmutable::parse('2026-11-20 10:00:00', 'UTC');
        $t1030 = CarbonImmutable::parse('2026-11-20 10:30:00', 'UTC');
        $t1100 = CarbonImmutable::parse('2026-11-20 11:00:00', 'UTC');
        $t1130 = CarbonImmutable::parse('2026-11-20 11:30:00', 'UTC');

        $writer = new AppointmentWriter(new AppointmentRepository, new ResourceAllocationRepository);

        // Given: Appointment with An + K2 from 10:00–11:00 at version 1
        $pairAnK2 = new CandidatePair(
            new TechnicianState($techAn->id, $techAn->name, Level::from($techAn->level)),
            new BayState($bayK2->id, $bayK2->code, $bayK2->bay_type),
        );

        $appointment = $writer->create(
            new NewAppointment(
                dealershipId: $world->dealership->id,
                customerId: $customer->id,
                vehicleId: $vehicle->id,
                serviceTypeId: $service->id,
                range: TimeRange::of($t1000, $t1100),
                createdByUserId: $user->id,
            ),
            [$pairAnK2],
            $now,
        );

        $this->assertSame(1, $appointment->version);
        $this->assertSame($techAn->id, $appointment->technician_id);
        $this->assertSame($bayK2->id, $appointment->bay_id);

        // When: moveTo 10:30–11:30 with pair (An, K2)
        // This shifts the range by 30 minutes, overlapping with the old range 10:00–11:00.
        // Part 1: No self-clash because old allocations are deactivated before new ones are inserted.
        $moved = $writer->moveTo(
            appointmentId: $appointment->id,
            expectedVersion: 1,
            newRange: TimeRange::of($t1030, $t1130),
            pairs: [$pairAnK2],
            event: AppointmentEventType::RESCHEDULED,
            outbox: OutboxEventType::AppointmentRescheduled,
            now: $now->addMinute(),
        );

        $this->assertSame(2, $moved->version);
        $this->assertSame($t1030->toIso8601String(), CarbonImmutable::parse($moved->start_at)->toIso8601String());
        $this->assertSame($t1130->toIso8601String(), CarbonImmutable::parse($moved->end_at)->toIso8601String());

        // Assert exactly 3 active allocations remain for this appointment (and 3 inactive old allocations)
        $activeAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $appointment->id)
            ->where('active', true)
            ->get();
        $this->assertCount(3, $activeAllocations);

        $inactiveAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $appointment->id)
            ->where('active', false)
            ->get();
        $this->assertCount(3, $inactiveAllocations);

        // Check M-04.2: No active overlapping allocations
        $overlaps = DB::select(<<<'SQL'
            SELECT r1.id AS id1, r2.id AS id2
            FROM resource_allocations r1
            JOIN resource_allocations r2
              ON r1.resource_type = r2.resource_type
             AND r1.resource_id = r2.resource_id
             AND r1.id < r2.id
             AND r1.active AND r2.active
             AND r1.period && r2.period
        SQL);
        $this->assertEmpty($overlaps, 'No active allocations may overlap');

        // Part 2: Second scenario - initial appointment with expectedVersion = 1,
        // but the first pair is taken by another appointment during moveTo.
        // It must retry with the next candidate pair using the SAME expectedVersion (no false 412).
        ['user' => $user2, 'customer' => $customer2, 'vehicle' => $vehicle2] = $world->customerWithVehicle();
        $appointment2 = $writer->create(
            new NewAppointment(
                dealershipId: $world->dealership->id,
                customerId: $customer2->id,
                vehicleId: $vehicle2->id,
                serviceTypeId: $service->id,
                range: TimeRange::of($t1000, $t1100),
                createdByUserId: $user2->id,
            ),
            [
                new CandidatePair(
                    new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
                    new BayState($bayK3->id, $bayK3->code, $bayK3->bay_type),
                ),
            ],
            $now,
        );
        $this->assertSame(1, $appointment2->version);

        // Candidate pairs for moveTo:
        // Pair 1: (An, K2) -> taken by $appointment (10:30–11:30)
        // Pair 2: (Dung, K3) -> free
        $pairDungK3 = new CandidatePair(
            new TechnicianState($techDung->id, $techDung->name, Level::from($techDung->level)),
            new BayState($bayK3->id, $bayK3->code, $bayK3->bay_type),
        );

        $moved2 = $writer->moveTo(
            appointmentId: $appointment2->id,
            expectedVersion: 1,
            newRange: TimeRange::of($t1030, $t1130),
            pairs: [$pairAnK2, $pairDungK3],
            event: AppointmentEventType::RESCHEDULED,
            outbox: OutboxEventType::AppointmentRescheduled,
            now: $now->addMinutes(2),
        );

        // Successfully moved using pair 2 (Dung, K3) with version 2
        $this->assertSame(2, $moved2->version);
        $this->assertSame($techDung->id, $moved2->technician_id);
        $this->assertSame($bayK3->id, $moved2->bay_id);

        $overlaps2 = DB::select(<<<'SQL'
            SELECT r1.id AS id1, r2.id AS id2
            FROM resource_allocations r1
            JOIN resource_allocations r2
              ON r1.resource_type = r2.resource_type
             AND r1.resource_id = r2.resource_id
             AND r1.id < r2.id
             AND r1.active AND r2.active
             AND r1.period && r2.period
        SQL);
        $this->assertEmpty($overlaps2, 'No active allocations may overlap');
    }
}
