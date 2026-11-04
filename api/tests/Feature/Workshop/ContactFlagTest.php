<?php

namespace Tests\Feature\Workshop;

use App\Domain\Booking\AppointmentEventType;
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

    /**
     * TC-12-20 (INV-12.9, D-12.8; Integration): given A booking flagged OVERRUN by an extend;
     * when Advisor reschedules it (F09); another flagged booking cancelled (F07);
     * then Flag cleared in the same versioned write; history rows present.
     */
    public function test_flag_cleared_by_reschedule_and_cancel(): void
    {
        $world = World::saigonD7();
        $world->onlyBays(['K1', 'K3', 'K4']);

        $advisor = $world->advisor();
        $foreman = $world->foreman();

        $techAn = $world->technician('An');
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        ['customer' => $customerX, 'vehicle' => $vehicleX] = $world->customerWithVehicle();
        ['customer' => $customerY, 'vehicle' => $vehicleY] = $world->customerWithVehicle();
        ['customer' => $customerZ, 'vehicle' => $vehicleZ] = $world->customerWithVehicle();

        $now = CarbonImmutable::parse('2026-11-02 09:30:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            // Job X: IN_PROGRESS 09:00-10:00 on K1 with An
            $appointmentX = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $now->setTime(9, 0),
                end: $now->setTime(10, 0),
                serviceType: $service,
                customer: $customerX,
                vehicle: $vehicleX,
                status: AppointmentStatus::IN_PROGRESS,
            );

            // Booking 1 (Y): CONFIRMED 10:00-11:00 on K1 with An
            $appointmentY = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $now->setTime(10, 0),
                end: $now->setTime(11, 0),
                serviceType: $service,
                customer: $customerY,
                vehicle: $vehicleY,
                status: AppointmentStatus::CONFIRMED,
            );

            // Extend X by 30 minutes -> Y cannot be placed elsewhere so Y is displaced with OVERRUN
            $extendResponse = $this->actingAs($foreman)->postJson(
                "/api/v1/appointments/{$appointmentX->id}/extend",
                ['minutes' => 30],
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );
            $extendResponse->assertStatus(200);

            $displacedY = $appointmentY->fresh();
            $this->assertNotNull($displacedY);
            $this->assertSame(2, $displacedY->version);
            $this->assertSame('OVERRUN', $displacedY->contact_required_reason);
            $this->assertSame(AppointmentStatus::CONFIRMED, $displacedY->status);

            // Booking 2 (Z): CONFIRMED 14:00-15:00 on K1 with An, displaced via AppointmentWriter::displace
            $appointmentZ = AppointmentFactory::create(
                dealership: $world->dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $now->setTime(14, 0),
                end: $now->setTime(15, 0),
                serviceType: $service,
                customer: $customerZ,
                vehicle: $vehicleZ,
                status: AppointmentStatus::CONFIRMED,
            );

            /** @var AppointmentWriterInterface $writer */
            $writer = app(AppointmentWriterInterface::class);
            $displacedZ = $writer->displace(
                appointmentId: $appointmentZ->id,
                expectedVersion: 1,
                reason: 'OVERRUN',
                now: $now,
            );
            $this->assertSame(2, $displacedZ->version);
            $this->assertSame('OVERRUN', $displacedZ->contact_required_reason);

            // 1. Advisor reschedules Y (F09) to 11:30-12:30
            $rescheduleStart = $now->setTime(11, 30)->toIso8601String();
            $rescheduleResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentY->id}/reschedule",
                [
                    'newStart' => $rescheduleStart,
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
                ->assertJsonPath('status', 'CONFIRMED')
                ->assertJsonPath('contactRequiredReason', null);

            $freshRescheduledY = $appointmentY->fresh();
            $this->assertNotNull($freshRescheduledY);
            $this->assertSame(3, $freshRescheduledY->version);
            $this->assertNull($freshRescheduledY->contact_required_reason);
            $this->assertSame(AppointmentStatus::CONFIRMED, $freshRescheduledY->status);

            // Verify history for Y: CONTACT_REQUIRED at v2, RESCHEDULED at v3
            $eventsY = DB::table('appointment_events')
                ->where('appointment_id', $appointmentY->id)
                ->orderBy('version', 'asc')
                ->get();
            $this->assertTrue($eventsY->contains('type', AppointmentEventType::CONTACT_REQUIRED->value));
            $this->assertTrue($eventsY->contains('type', AppointmentEventType::RESCHEDULED->value));

            $eventRescheduled = $eventsY->firstWhere('type', AppointmentEventType::RESCHEDULED->value);
            $this->assertNotNull($eventRescheduled);
            $this->assertSame(3, $eventRescheduled->version);

            // 2. Advisor cancels Z (F07)
            $cancelResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentZ->id}/cancel",
                [
                    'reason' => 'CUSTOMER_REQUEST',
                    'note' => 'Customer requested cancellation after delay',
                ],
                [
                    'If-Match' => '"2"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ]
            );

            $cancelResponse->assertStatus(200)
                ->assertHeader('ETag', '"3"')
                ->assertJsonPath('version', 3)
                ->assertJsonPath('status', 'CANCELLED')
                ->assertJsonPath('contactRequiredReason', null);

            $freshCancelledZ = $appointmentZ->fresh();
            $this->assertNotNull($freshCancelledZ);
            $this->assertSame(3, $freshCancelledZ->version);
            $this->assertNull($freshCancelledZ->contact_required_reason);
            $this->assertSame(AppointmentStatus::CANCELLED, $freshCancelledZ->status);

            // Active allocations for Z: 0
            $activeAllocationsZ = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentZ->id)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocationsZ);

            // Verify history for Z: CONTACT_REQUIRED at v2, CANCELLED at v3
            $eventsZ = DB::table('appointment_events')
                ->where('appointment_id', $appointmentZ->id)
                ->orderBy('version', 'asc')
                ->get();
            $this->assertTrue($eventsZ->contains('type', AppointmentEventType::CONTACT_REQUIRED->value));
            $this->assertTrue($eventsZ->contains('type', AppointmentEventType::CANCELLED->value));

            $eventCancelled = $eventsZ->firstWhere('type', AppointmentEventType::CANCELLED->value);
            $this->assertNotNull($eventCancelled);
            $this->assertSame(3, $eventCancelled->version);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-12-21 (D-12.14; Integration): given 2 flagged today, 1 flagged in 3 days,
     * 1 flagged yesterday, 3 normal bookings;
     * when GET /dealerships/{d}/appointments?contactRequired=true (no date);
     * then The 3 flagged from today on, by start; meta.total 3.
     */
    public function test_contact_list_filter(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $advisor = $world->advisor();

        $techAn = $world->technician('An');
        $bayK1 = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        /** @var AppointmentWriterInterface $writer */
        $writer = app(AppointmentWriterInterface::class);

        // Dealership timezone Asia/Ho_Chi_Minh: 2026-11-02 08:00
        $today = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($today);

        try {
            $yesterday = $today->subDay();
            $in3Days = $today->addDays(3);

            // 1. Flagged booking today at 10:00
            $flaggedToday1 = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $today->setTime(10, 0),
                end: $today->setTime(11, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );
            $writer->displace($flaggedToday1->id, 1, 'OVERRUN', $today);

            // 2. Flagged booking today at 14:00
            $flaggedToday2 = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $today->setTime(14, 0),
                end: $today->setTime(15, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );
            $writer->displace($flaggedToday2->id, 1, 'OVERRUN', $today);

            // 3. Flagged booking in 3 days (2026-11-05 09:00)
            $flaggedIn3Days = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $in3Days->setTime(9, 0),
                end: $in3Days->setTime(10, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );
            $writer->displace($flaggedIn3Days->id, 1, 'OVERRUN', $today);

            // 4. Flagged booking yesterday (2026-11-01 10:00)
            $flaggedYesterday = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $yesterday->setTime(10, 0),
                end: $yesterday->setTime(11, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );
            $writer->displace($flaggedYesterday->id, 1, 'OVERRUN', $today);

            // 5. Normal booking yesterday (no flag)
            $normalYesterday = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $yesterday->setTime(14, 0),
                end: $yesterday->setTime(15, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            // 6. Normal booking today (no flag)
            $normalToday = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $today->setTime(11, 30),
                end: $today->setTime(12, 30),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            // 7. Normal booking in 1 day (no flag)
            $normalTomorrow = AppointmentFactory::create(
                dealership: $dealership,
                technician: $techAn,
                bay: $bayK1,
                start: $today->addDay()->setTime(10, 0),
                end: $today->addDay()->setTime(11, 0),
                serviceType: $service,
                status: AppointmentStatus::CONFIRMED,
            );

            // when GET /dealerships/{d}/appointments?contactRequired=true (no date)
            $response = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/appointments?contactRequired=true"
            );

            $response->assertStatus(200);
            $response->assertJsonPath('meta.total', 3);

            /** @var list<array<string, mixed>> $data */
            $data = $response->json('data');
            $this->assertCount(3, $data);

            // Assert ordered by start_at ASC: flaggedToday1, flaggedToday2, flaggedIn3Days
            $this->assertSame($flaggedToday1->id, $data[0]['id']);
            $this->assertSame($flaggedToday2->id, $data[1]['id']);
            $this->assertSame($flaggedIn3Days->id, $data[2]['id']);

            foreach ($data as $item) {
                $this->assertSame('OVERRUN', $item['contactRequiredReason']);
                $this->assertSame('CONFIRMED', $item['status']);
            }

            // Verify yesterday's flagged and normal bookings are not present
            $returnedIds = array_column($data, 'id');
            $this->assertNotContains($flaggedYesterday->id, $returnedIds);
            $this->assertNotContains($normalYesterday->id, $returnedIds);
            $this->assertNotContains($normalToday->id, $returnedIds);
            $this->assertNotContains($normalTomorrow->id, $returnedIds);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
