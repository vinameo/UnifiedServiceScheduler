<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use App\Domain\MasterData\Level;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\World;
use Tests\TestCase;

class ReferenceTest extends TestCase
{
    use DatabaseTruncation;

    public function test_D_04_6_reference_uses_local_date(): void
    {
        $world = World::saigonD7(); // Asia/Ho_Chi_Minh is UTC+7
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        /** @var AppointmentWriterInterface $writer */
        $writer = app(AppointmentWriterInterface::class);

        // Booking 1: 2026-11-02 06:30 local (= 2026-11-01 23:30Z)
        $startUtc1 = CarbonImmutable::parse('2026-11-01 23:30:00', 'UTC');
        $endUtc1 = $startUtc1->addHour();
        $range1 = TimeRange::of($startUtc1, $endUtc1);

        $pair = new CandidatePair(
            new TechnicianState($tech->id, $tech->name, Level::from($tech->level)),
            new BayState($bay->id, $bay->code, $bay->bay_type),
        );

        $now = CarbonImmutable::parse('2026-10-25 10:00:00', 'UTC');

        $apt1 = $writer->create(new NewAppointment(
            dealershipId: $world->dealership->id,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            serviceTypeId: $service->id,
            range: $range1,
            createdByUserId: $user->id,
        ), [$pair], $now);

        // Booking 2: 2026-11-03 09:00 local (= 2026-11-03 02:00Z)
        $startUtc2 = CarbonImmutable::parse('2026-11-03 02:00:00', 'UTC');
        $endUtc2 = $startUtc2->addHour();
        $range2 = TimeRange::of($startUtc2, $endUtc2);

        $apt2 = $writer->create(new NewAppointment(
            dealershipId: $world->dealership->id,
            customerId: $customer->id,
            vehicleId: $vehicle->id,
            serviceTypeId: $service->id,
            range: $range2,
            createdByUserId: $user->id,
        ), [$pair], $now);

        $this->assertMatchesRegularExpression('/^APT-20261102-\d{4,}$/', $apt1->reference);
        $this->assertMatchesRegularExpression('/^APT-20261103-\d{4,}$/', $apt2->reference);
        $this->assertNotSame($apt1->reference, $apt2->reference);
    }
}
