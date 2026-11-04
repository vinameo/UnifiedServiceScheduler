<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Services\AppointmentWriterInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class ContactFlagTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_12_14_displaced_booking_needs_reschedule_before_check_in(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $techAn = $world->technician('An');
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');
        ['customer' => $customerY, 'vehicle' => $vehicleY] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 08:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Y: CONFIRMED booking today 10:00-11:00
            $startAt = $now->setTime(10, 0);
            $endAt = $now->setTime(11, 0);

            $appointmentY = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $startAt,
                end: $endAt,
                serviceType: $service,
                customer: $customerY,
                vehicle: $vehicleY,
                status: AppointmentStatus::CONFIRMED,
            );

            $this->assertSame(1, $appointmentY->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $appointmentY->status);

            // Displace Y via AppointmentWriter::displace (OVERRUN, 0 active allocations)
            /** @var AppointmentWriterInterface $writer */
            $writer = app(AppointmentWriterInterface::class);
            $displacedY = $writer->displace(
                appointmentId: $appointmentY->id,
                expectedVersion: 1,
                reason: 'OVERRUN',
                now: $now,
            );

            $this->assertSame(2, $displacedY->version);
            $this->assertSame('OVERRUN', $displacedY->contact_required_reason);
            $this->assertSame(AppointmentStatus::CONFIRMED, $displacedY->status);

            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentY->id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocations);

            // 1. Advisor checks Y in -> 422 NEEDS_RESCHEDULE, Y unchanged
            $checkInDisplaced = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentY->id}/check-in",
                [],
                [
                    'If-Match' => '"2"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $checkInDisplaced->assertStatus(422)
                ->assertJsonPath('code', 'NEEDS_RESCHEDULE');

            $freshY = $appointmentY->fresh();
            $this->assertNotNull($freshY);
            $this->assertSame(2, $freshY->version);
            $this->assertSame('OVERRUN', $freshY->contact_required_reason);
            $this->assertSame(AppointmentStatus::CONFIRMED, $freshY->status);

            // 2. Advisor reschedules Y to the same time (a bay is free again)
            $yStartIso = $startAt->toIso8601String();
            $rescheduleResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentY->id}/reschedule",
                [
                    'newStart' => $yStartIso,
                    'keepTechnician' => true,
                ],
                [
                    'If-Match' => '"2"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $rescheduleResponse->assertStatus(200)
                ->assertHeader('ETag', '"3"')
                ->assertJsonPath('version', 3)
                ->assertJsonPath('status', 'CONFIRMED');

            $freshRescheduledY = $appointmentY->fresh();
            $this->assertNotNull($freshRescheduledY);
            $this->assertSame(3, $freshRescheduledY->version);
            $this->assertNull($freshRescheduledY->contact_required_reason);

            $activeAllocationsAfterReschedule = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentY->id)
                ->where('active', true)
                ->count();
            $this->assertSame(3, $activeAllocationsAfterReschedule);

            // 3. Advisor checks in Y -> 200
            $checkInResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentY->id}/check-in",
                [],
                [
                    'If-Match' => '"3"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $checkInResponse->assertStatus(200)
                ->assertHeader('ETag', '"4"')
                ->assertJsonPath('status', 'CHECKED_IN')
                ->assertJsonPath('version', 4);

            $freshFinalY = $appointmentY->fresh();
            $this->assertNotNull($freshFinalY);
            $this->assertSame(AppointmentStatus::CHECKED_IN, $freshFinalY->status);
            $this->assertNotNull($freshFinalY->checked_in_at);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
