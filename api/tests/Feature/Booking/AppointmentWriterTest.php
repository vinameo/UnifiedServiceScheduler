<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AppointmentRepository;
use App\Domain\Booking\Repositories\ResourceAllocationRepository;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\AppointmentWriter;
use App\Domain\MasterData\Level;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
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
}
