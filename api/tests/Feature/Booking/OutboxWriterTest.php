<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\BayState;
use App\Domain\Allocation\CandidatePair;
use App\Domain\Allocation\TechnicianState;
use App\Domain\Allocation\TimeRange;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\NewAppointment;
use App\Domain\Booking\Repositories\AppointmentRepository;
use App\Domain\Booking\Repositories\ResourceAllocationRepository;
use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\Booking\ResourceType;
use App\Domain\Booking\Services\AppointmentWriter;
use App\Domain\MasterData\Level;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class OutboxWriterTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_04_20_outbox_rows_commit_with_the_booking(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-22 09:00:00', 'UTC');

        // Booking 1: Create a CONFIRMED booking via POST /api/v1/_testing/appointments
        $response = $this->actingAs($user)
            ->postJson('/api/v1/_testing/appointments', [
                'dealershipId' => $world->dealership->id,
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $service->id,
                'start' => $now->toIso8601String(),
            ], [
                'Accept' => 'application/json',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

        $response->assertStatus(201);
        $apptId1 = $response->json('id');
        $this->assertNotNull($apptId1);

        // Booking 1: 3 outbox rows (SMS, EMAIL, DMS) with one event_id, PENDING, payload without phone/email
        $outboxRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId1)
            ->get();

        $this->assertCount(3, $outboxRows);

        $eventIds = [];
        $channels = [];
        foreach ($outboxRows as $row) {
            $eventIds[] = $row->event_id;
            $channels[] = $row->channel;
            $this->assertSame('AppointmentConfirmed', $row->event_type);
            $this->assertSame('PENDING', $row->status);
            $this->assertSame(0, $row->attempts);

            /** @var array<string, mixed> $payload */
            $payload = json_decode((string) $row->payload, true);
            $this->assertArrayNotHasKey('phone', $payload);
            $this->assertArrayNotHasKey('email', $payload);
            $this->assertArrayHasKey('appointmentId', $payload);
            $this->assertArrayHasKey('reference', $payload);
        }

        // Shared event_id across all 3 channels
        $this->assertCount(1, array_unique($eventIds));
        sort($channels);
        $this->assertSame(['DMS', 'EMAIL', 'SMS'], $channels);

        // Booking 2: forced to roll back
        // Create an AppointmentWriter with a failing allocation repo that throws RuntimeException on insert
        $failingAllocationRepo = new class(new ResourceAllocationRepository) implements ResourceAllocationRepositoryInterface
        {
            public function __construct(private ResourceAllocationRepository $inner) {}

            public function forDealership(string $dealershipId, TimeRange $window, CarbonImmutable $now): array
            {
                return $this->inner->forDealership($dealershipId, $window, $now);
            }

            public function insertFor(string $appointmentId, string $dealershipId, array $allocations, CarbonImmutable $now): void
            {
                throw new RuntimeException('Forced failure inside transaction');
            }

            public function deactivateFor(string $appointmentId, CarbonImmutable $now): int
            {
                return $this->inner->deactivateFor($appointmentId, $now);
            }

            public function deactivateType(string $appointmentId, ResourceType|string $resourceType, CarbonImmutable $now): int
            {
                return $this->inner->deactivateType($appointmentId, $resourceType, $now);
            }

            public function upcomingForTechnician(string $technicianId, CarbonImmutable $now): array
            {
                return $this->inner->upcomingForTechnician($technicianId, $now);
            }
        };

        $writer = new AppointmentWriter(new AppointmentRepository, $failingAllocationRepo);
        $tech = $world->technician('Dũng');
        $bay = $world->bay('K2');
        $slot2Start = $now->addHours(2);
        $range2 = TimeRange::of($slot2Start, $slot2Start->addHour());

        $newAppointment2 = new NewAppointment(
            dealershipId: (string) $world->dealership->id,
            customerId: (string) $customer->id,
            vehicleId: (string) $vehicle->id,
            serviceTypeId: (string) $service->id,
            range: $range2,
            status: AppointmentStatus::CONFIRMED,
            expiresAt: null,
            reminderOptIn: true,
            source: 'CUSTOMER',
            createdByUserId: (int) $user->id,
        );

        $pair = new CandidatePair(
            new TechnicianState((string) $tech->id, (string) $tech->name, Level::from((int) $tech->level)),
            new BayState((string) $bay->id, (string) $bay->code, $bay->bay_type),
        );

        $thrown = false;
        try {
            $writer->create($newAppointment2, [$pair], $now);
        } catch (RuntimeException $e) {
            $thrown = true;
        }

        $this->assertTrue($thrown, 'Expected RuntimeException to be thrown');

        // Total outbox rows in DB should still be exactly 3 (from Booking 1 only; Booking 2 left 0 rows)
        $this->assertSame(3, DB::table('outbox_messages')->count());
        $this->assertSame(1, DB::table('appointments')->count());
    }
}
