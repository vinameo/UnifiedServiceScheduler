<?php

namespace Tests\Feature\Booking;

use App\Domain\Allocation\ResourceSnapshot;
use App\Domain\Booking\AppointmentStatus;
use App\Domain\Booking\Events\AllocationRetried;
use App\Domain\Booking\Repositories\AllocationSnapshotRepositoryInterface;
use App\Models\Appointment;
use App\Models\Bay;
use App\Models\Customer;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class RescheduleTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-09-01 (AC-09.1, INV-09.1): Failed move keeps the original booking and its allocations untouched.
     */
    public function test_AC_09_1_failed_move_keeps_the_original(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);

        $advisor = $world->advisor();
        ['customer' => $customerX, 'vehicle' => $vehicleX] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H11111', 'licence_plate' => '51H-111.11']
        );
        ['customer' => $customerY, 'vehicle' => $vehicleY] = $world->customerWithVehicle(
            null,
            ['make' => 'Honda', 'model' => 'City', 'plate_key' => '51K22222', 'licence_plate' => '51K-222.22']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking X at 09:00
            $bookXResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerX->id,
                'vehicleId' => $vehicleX->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookXResponse->assertStatus(201);
            $appointmentXId = $bookXResponse->json('id');

            // 2. Create booking Y at 14:00 (takes An, since only An is active)
            $bookYResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerY->id,
                'vehicleId' => $vehicleY->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot1400,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookYResponse->assertStatus(201);

            // Record snapshot of X before reschedule
            /** @var Appointment $rowBefore */
            $rowBefore = Appointment::findOrFail($appointmentXId);
            $this->assertSame(1, $rowBefore->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $rowBefore->status);

            $allocationsBefore = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $this->assertNotEmpty($allocationsBefore);

            $eventsCountBefore = DB::table('appointment_events')
                ->where('appointment_id', $appointmentXId)
                ->count();
            $outboxCountBefore = DB::table('outbox_messages')
                ->where('appointment_id', $appointmentXId)
                ->count();

            // When: Advisor reschedules X to 14:00 (which is taken)
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentXId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then: 409 NO_AVAILABILITY with alternatives
            $rescheduleResponse->assertStatus(409);
            $this->assertSame('NO_AVAILABILITY', $rescheduleResponse->json('code'));
            $this->assertIsArray($rescheduleResponse->json('extra.alternatives') ?? $rescheduleResponse->json('alternatives'));

            // X row is identical
            /** @var Appointment $rowAfter */
            $rowAfter = Appointment::findOrFail($appointmentXId);
            $this->assertSame($rowBefore->start_at->toIso8601String(), $rowAfter->start_at->toIso8601String());
            $this->assertSame($rowBefore->end_at->toIso8601String(), $rowAfter->end_at->toIso8601String());
            $this->assertSame($rowBefore->technician_id, $rowAfter->technician_id);
            $this->assertSame($rowBefore->bay_id, $rowAfter->bay_id);
            $this->assertSame(1, $rowAfter->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $rowAfter->status);

            // X allocations unchanged and active
            $allocationsAfter = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $this->assertEquals($allocationsBefore, $allocationsAfter);

            // No new history or outbox rows
            $this->assertSame($eventsCountBefore, DB::table('appointment_events')->where('appointment_id', $appointmentXId)->count());
            $this->assertSame($outboxCountBefore, DB::table('outbox_messages')->where('appointment_id', $appointmentXId)->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-02 (AC-09.2, INV-09.3): Move inside own slot does not self-clash.
     */
    public function test_AC_09_2_move_inside_own_slot(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $world->onlyBays(['K2']);

        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot1000 = '2026-11-03T10:00:00+07:00';
            $slot1030 = '2026-11-03T10:30:00+07:00';

            // Given X 10:00–11:00 on An + K2; K2 free 11:00–11:30
            $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot1000,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookResponse->assertStatus(201);
            $appointmentId = $bookResponse->json('id');
            $bayK2Id = $bookResponse->json('bay.id');

            // When: Reschedule to 10:30 (overlaps own 10:00–11:00 range)
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
                'newStart' => $slot1030,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then: 200, X 10:30–11:30, still K2
            $rescheduleResponse->assertStatus(200);
            $rescheduleResponse->assertHeader('ETag', '"2"');
            $this->assertSame(2, $rescheduleResponse->json('version'));
            $this->assertSame('2026-11-03T10:30:00+07:00', $rescheduleResponse->json('start'));
            $this->assertSame('2026-11-03T11:30:00+07:00', $rescheduleResponse->json('end'));
            $this->assertSame($bayK2Id, $rescheduleResponse->json('bay.id'));

            // Check DB
            /** @var Appointment $row */
            $row = Appointment::findOrFail($appointmentId);
            $this->assertSame(2, $row->version);
            $this->assertSame('2026-11-03 10:30:00', $row->start_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-03 11:30:00', $row->end_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'));
            $this->assertSame($bayK2Id, $row->bay_id);

            // Overlap query 0
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-15 (AC-09.11): Exactly 1 RESCHEDULED history row with from/to and 1 AppointmentRescheduled event; reference unchanged; none on failure.
     */
    public function test_AC_09_11_history_and_outbox(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // Given X v1
            $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookResponse->assertStatus(201);
            $appointmentId = $bookResponse->json('id');
            $originalReference = $bookResponse->json('reference');
            $techBefore = $bookResponse->json('technician.id');
            $bayBefore = $bookResponse->json('bay.id');

            // When: Reschedule to 14:00
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            $rescheduleResponse->assertStatus(200);
            $this->assertSame($originalReference, $rescheduleResponse->json('reference'));
            $this->assertSame(2, $rescheduleResponse->json('version'));

            // 1 RESCHEDULED history row with from/to
            $events = DB::table('appointment_events')
                ->where('appointment_id', $appointmentId)
                ->where('type', 'RESCHEDULED')
                ->get();

            $this->assertCount(1, $events);
            $event = $events->first();
            $this->assertNotNull($event);
            $this->assertSame(2, $event->version);
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $event->from_status);
            $this->assertSame(AppointmentStatus::CONFIRMED->value, $event->to_status);
            $this->assertSame($advisor->id, $event->actor_user_id);
            $this->assertSame('SERVICE_ADVISOR', $event->actor_role);

            $eventData = json_decode((string) $event->data, true);
            $this->assertIsArray($eventData);
            $this->assertArrayHasKey('from', $eventData);
            $this->assertArrayHasKey('to', $eventData);
            $this->assertSame($techBefore, $eventData['from']['technicianId']);
            $this->assertSame($bayBefore, $eventData['from']['bayId']);
            $this->assertSame($rescheduleResponse->json('technician.id'), $eventData['to']['technicianId']);
            $this->assertSame($rescheduleResponse->json('bay.id'), $eventData['to']['bayId']);

            // 1 AppointmentRescheduled event_id across 3 channels (SMS, EMAIL, DMS)
            $outboxMessages = DB::table('outbox_messages')
                ->where('appointment_id', $appointmentId)
                ->where('event_type', 'AppointmentRescheduled')
                ->get();

            $this->assertCount(3, $outboxMessages);
            $eventIds = $outboxMessages->pluck('event_id')->unique();
            $this->assertCount(1, $eventIds);

            $channels = $outboxMessages->pluck('channel')->all();
            $this->assertEqualsCanonicalizing(['SMS', 'EMAIL', 'DMS'], $channels);

            // Verify payload
            foreach ($outboxMessages as $msg) {
                $payload = json_decode((string) $msg->payload, true);
                $this->assertSame($appointmentId, $payload['appointmentId']);
                $this->assertSame($originalReference, $payload['reference']);
                $this->assertArrayHasKey('start', $payload);
                $this->assertArrayHasKey('end', $payload);
            }

            // On a failed move (e.g. invalid version or time taken), none of these are written
            $outboxCountBeforeFail = DB::table('outbox_messages')->where('appointment_id', $appointmentId)->count();
            $eventsCountBeforeFail = DB::table('appointment_events')->where('appointment_id', $appointmentId)->count();

            $failResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
                'newStart' => '2026-11-03T15:00:00+07:00',
            ], [
                'If-Match' => '"1"', // Stale version (current is 2)
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $failResponse->assertStatus(412);

            $this->assertSame($outboxCountBeforeFail, DB::table('outbox_messages')->where('appointment_id', $appointmentId)->count());
            $this->assertSame($eventsCountBeforeFail, DB::table('appointment_events')->where('appointment_id', $appointmentId)->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-04 (AC-09.4): Reschedule keeps the same technician when free, even if another is ranked first normally.
     */
    public function test_AC_09_4_keeps_the_same_technician(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An', 'Dũng']);

        $an = $world->technician('An');
        $dung = $world->technician('Dũng');
        $this->assertLessThan($dung->level, $an->level);

        $advisor = $world->advisor();
        ['customer' => $customerX, 'vehicle' => $vehicleX] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H11111', 'licence_plate' => '51H-111.11']
        );
        ['customer' => $customerY, 'vehicle' => $vehicleY] = $world->customerWithVehicle(
            null,
            ['make' => 'Honda', 'model' => 'City', 'plate_key' => '51K22222', 'licence_plate' => '51K-222.22']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking Y at 09:00 (takes An because An is Level 2, lower than Dũng Level 3)
            $bookY = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerY->id,
                'vehicleId' => $vehicleY->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookY->assertStatus(201);
            $this->assertSame($an->id, $bookY->json('technician.id'));

            // 2. Create booking X at 09:00 (takes Dũng because An is busy)
            $bookX = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerX->id,
                'vehicleId' => $vehicleX->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookX->assertStatus(201);
            $appointmentXId = $bookX->json('id');
            $this->assertSame($dung->id, $bookX->json('technician.id'));

            // 3. At 14:00, both An and Dũng are free. An would be ranked first normally (Level 2 < Level 3).
            // When Reschedule X to 14:00 with keepTechnician = true:
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentXId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then 200 with Dũng
            $rescheduleResponse->assertStatus(200);
            $this->assertSame($dung->id, $rescheduleResponse->json('technician.id'));

            /** @var Appointment $row */
            $row = Appointment::findOrFail($appointmentXId);
            $this->assertSame($dung->id, $row->technician_id);
            $this->assertSame(2, $row->version);

            // Active TECHNICIAN allocation is Dũng
            $activeTechAlloc = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('resource_type', 'TECHNICIAN')
                ->where('active', true)
                ->first();
            $this->assertNotNull($activeTechAlloc);
            $this->assertSame($dung->id, $activeTechAlloc->resource_id);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-05 (AC-09.5, D-09.6): Reschedule falls back to another qualified technician when preferred is busy.
     */
    public function test_AC_09_5_other_technician_when_preferred_is_busy(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An', 'Dũng']);

        $an = $world->technician('An');
        $dung = $world->technician('Dũng');

        $advisor = $world->advisor();
        ['customer' => $customerX, 'vehicle' => $vehicleX] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H11111', 'licence_plate' => '51H-111.11']
        );
        ['customer' => $customerY, 'vehicle' => $vehicleY] = $world->customerWithVehicle(
            null,
            ['make' => 'Honda', 'model' => 'City', 'plate_key' => '51K22222', 'licence_plate' => '51K-222.22']
        );
        ['customer' => $customerZ, 'vehicle' => $vehicleZ] = $world->customerWithVehicle(
            null,
            ['make' => 'Mazda', 'model' => '3', 'plate_key' => '51M33333', 'licence_plate' => '51M-333.33']
        );
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking Y at 09:00 (takes An)
            $bookY = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerY->id,
                'vehicleId' => $vehicleY->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookY->assertStatus(201);
            $this->assertSame($an->id, $bookY->json('technician.id'));

            // 2. Create booking X at 09:00 (takes Dũng)
            $bookX = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerX->id,
                'vehicleId' => $vehicleX->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookX->assertStatus(201);
            $appointmentXId = $bookX->json('id');
            $this->assertSame($dung->id, $bookX->json('technician.id'));

            // 3. Make Dũng busy at 14:00 with another booking Z:
            // Temporarily restrict to Dũng to book Z at 14:00
            $world->onlyTechnicians(['Dũng']);
            $bookZ = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customerZ->id,
                'vehicleId' => $vehicleZ->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot1400,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookZ->assertStatus(201);
            $this->assertSame($dung->id, $bookZ->json('technician.id'));

            // Re-enable An so both An and Dũng are active, but Dũng is busy at 14:00 and An is free
            $world->onlyTechnicians(['An', 'Dũng']);

            // When Reschedule X to 14:00 (keepTechnician true by default or explicit)
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentXId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then 200 with An (preferred Dũng is busy, An is free and qualified)
            $rescheduleResponse->assertStatus(200);
            $this->assertSame($an->id, $rescheduleResponse->json('technician.id'));

            /** @var Appointment $row */
            $row = Appointment::findOrFail($appointmentXId);
            $this->assertSame($an->id, $row->technician_id);
            $this->assertSame(2, $row->version);

            // technician_id matches the active TECHNICIAN allocation
            $activeTechAlloc = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('resource_type', 'TECHNICIAN')
                ->where('active', true)
                ->first();
            $this->assertNotNull($activeTechAlloc);
            $this->assertSame($an->id, $activeTechAlloc->resource_id);
            $this->assertSame($row->technician_id, $activeTechAlloc->resource_id);

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-06 (AC-09.6, D-09.10): Reschedule fails with 409 VEHICLE_ALREADY_BOOKED when the vehicle has another booking.
     */
    public function test_AC_09_6_vehicle_busy_at_new_time(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking X for vehicle V at 09:00–10:00
            $bookX = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookX->assertStatus(201);
            $appointmentXId = $bookX->json('id');

            // 2. Create booking Y for the SAME vehicle V at 14:00–15:00
            $bookY = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot1400,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookY->assertStatus(201);
            $appointmentYId = $bookY->json('id');
            $referenceY = $bookY->json('reference');

            // Record snapshot of X before reschedule
            /** @var Appointment $rowBefore */
            $rowBefore = Appointment::findOrFail($appointmentXId);
            $allocationsBefore = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $eventsCountBefore = DB::table('appointment_events')
                ->where('appointment_id', $appointmentXId)
                ->count();
            $outboxCountBefore = DB::table('outbox_messages')
                ->where('appointment_id', $appointmentXId)
                ->count();

            // When: Advisor reschedules X to 14:00 (where vehicle V is already booked by Y)
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentXId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then: 409 VEHICLE_ALREADY_BOOKED
            $rescheduleResponse->assertStatus(409);
            $this->assertSame('VEHICLE_ALREADY_BOOKED', $rescheduleResponse->json('code'));
            $this->assertSame($appointmentYId, $rescheduleResponse->json('existingAppointment.id'));
            $this->assertSame($referenceY, $rescheduleResponse->json('existingAppointment.reference'));

            // X is unchanged: status, start, end, technician, bay, version
            /** @var Appointment $rowAfter */
            $rowAfter = Appointment::findOrFail($appointmentXId);
            $this->assertSame($rowBefore->start_at->toIso8601String(), $rowAfter->start_at->toIso8601String());
            $this->assertSame($rowBefore->end_at->toIso8601String(), $rowAfter->end_at->toIso8601String());
            $this->assertSame($rowBefore->technician_id, $rowAfter->technician_id);
            $this->assertSame($rowBefore->bay_id, $rowAfter->bay_id);
            $this->assertSame($rowBefore->version, $rowAfter->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $rowAfter->status);

            // Allocations for X unchanged and active
            $allocationsAfter = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $this->assertEquals($allocationsBefore, $allocationsAfter);

            // No events or outbox messages written for failed move
            $this->assertSame($eventsCountBefore, DB::table('appointment_events')->where('appointment_id', $appointmentXId)->count());
            $this->assertSame($outboxCountBefore, DB::table('outbox_messages')->where('appointment_id', $appointmentXId)->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-08 (INV-09.3): Move earlier overlapping own slot succeeds and keeps bay if free.
     */
    public function test_move_earlier_overlapping_own_slot(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['An']);
        $world->onlyBays(['K2']);

        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot1000 = '2026-11-03T10:00:00+07:00';
            $slot0930 = '2026-11-03T09:30:00+07:00';

            // Given X 10:00–11:00 on K2
            $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot1000,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookResponse->assertStatus(201);
            $appointmentId = $bookResponse->json('id');
            $bayK2Id = $bookResponse->json('bay.id');

            // When: Reschedule to 09:30 (overlaps own 10:00–11:00 start: 09:30–10:30 overlaps [10:00, 10:30))
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentId}/reschedule", [
                'newStart' => $slot0930,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then: 200, X 09:30–10:30, still K2
            $rescheduleResponse->assertStatus(200);
            $rescheduleResponse->assertHeader('ETag', '"2"');
            $this->assertSame(2, $rescheduleResponse->json('version'));
            $this->assertSame('2026-11-03T09:30:00+07:00', $rescheduleResponse->json('start'));
            $this->assertSame('2026-11-03T10:30:00+07:00', $rescheduleResponse->json('end'));
            $this->assertSame($bayK2Id, $rescheduleResponse->json('bay.id'));

            // Check DB
            /** @var Appointment $row */
            $row = Appointment::findOrFail($appointmentId);
            $this->assertSame(2, $row->version);
            $this->assertSame('2026-11-03 09:30:00', $row->start_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'));
            $this->assertSame('2026-11-03 10:30:00', $row->end_at->setTimezone('Asia/Ho_Chi_Minh')->format('Y-m-d H:i:s'));
            $this->assertSame($bayK2Id, $row->bay_id);

            // Active allocations count 3 and time matches 09:30–10:30
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentId)
                ->where('active', true)
                ->get();
            $this->assertCount(3, $activeAllocations);

            // Overlap query 0
            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-07 (D-09.10, INV-09.2): All candidate technicians get conflicting allocations inserted by hand
     * after the snapshot; when Reschedule; then 409 NO_AVAILABILITY SLOT_TAKEN after 3 tries with the same
     * expected version; alternatives present; appointment unchanged.
     */
    public function test_three_tries_then_slot_taken_with_alternatives(): void
    {
        Event::fake([AllocationRetried::class]);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking X at 09:00
            $bookX = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookX->assertStatus(201);
            $appointmentXId = $bookX->json('id');

            // Record snapshot of X before reschedule
            /** @var Appointment $rowBefore */
            $rowBefore = Appointment::findOrFail($appointmentXId);
            $this->assertSame(1, $rowBefore->version);

            $allocationsBefore = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $eventsCountBefore = DB::table('appointment_events')
                ->where('appointment_id', $appointmentXId)
                ->count();
            $outboxCountBefore = DB::table('outbox_messages')
                ->where('appointment_id', $appointmentXId)
                ->count();

            // Decorate snapshot repository so that after the snapshot is taken,
            // conflicting allocations for all candidate technicians are inserted by hand
            $realSnapshotRepo = app(AllocationSnapshotRepositoryInterface::class);
            $injected = false;
            $bayK1 = $world->bay('K1');

            $this->app->bind(AllocationSnapshotRepositoryInterface::class, function () use (
                $realSnapshotRepo,
                &$injected,
                $customer,
                $user,
                $serviceType,
                $bayK1,
                $now,
            ) {
                return new class($realSnapshotRepo, $injected, $customer, $user, $serviceType, $bayK1, $now) implements AllocationSnapshotRepositoryInterface
                {
                    public function __construct(
                        private readonly AllocationSnapshotRepositoryInterface $real,
                        private bool &$injected,
                        private readonly Customer $customer,
                        private readonly User $user,
                        private readonly ServiceType $serviceType,
                        private readonly Bay $bay,
                        private readonly CarbonImmutable $now,
                    ) {}

                    public function forDay(string $dealershipId, string $localDate, CarbonImmutable $now): ResourceSnapshot
                    {
                        $snapshot = $this->real->forDay($dealershipId, $localDate, $now);

                        if (! $this->injected) {
                            $this->injected = true;
                            $targetStart = CarbonImmutable::parse('2026-11-03 14:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');
                            $targetEnd = CarbonImmutable::parse('2026-11-03 15:00:00', 'Asia/Ho_Chi_Minh')->setTimezone('UTC');

                            $technicians = Technician::where('dealership_id', $dealershipId)
                                ->where('active', true)
                                ->get();

                            foreach ($technicians as $tech) {
                                $otherVehicle = Vehicle::factory()->create(['customer_id' => $this->customer->id]);
                                $dummyApptId = (string) Str::uuid();

                                DB::table('appointments')->insert([
                                    'id' => $dummyApptId,
                                    'reference' => 'APT-20261103-'.Str::upper(Str::random(6)),
                                    'dealership_id' => $dealershipId,
                                    'customer_id' => $this->customer->id,
                                    'vehicle_id' => $otherVehicle->id,
                                    'service_type_id' => $this->serviceType->id,
                                    'technician_id' => $tech->id,
                                    'bay_id' => $this->bay->id,
                                    'status' => AppointmentStatus::CONFIRMED->value,
                                    'start_at' => $targetStart,
                                    'end_at' => $targetEnd,
                                    'version' => 1,
                                    'source' => 'STAFF',
                                    'created_by_user_id' => $this->user->id,
                                    'created_at' => $this->now,
                                    'updated_at' => $this->now,
                                ]);

                                DB::statement(<<<'SQL'
                                    INSERT INTO resource_allocations (
                                        id, appointment_id, dealership_id, resource_type, resource_id, period, active, created_at
                                    ) VALUES (
                                        :id, :appointment_id, :dealership_id, 'TECHNICIAN', :tech_id, tstzrange(:start, :end, '[)'), true, :created_at
                                    )
                                SQL, [
                                    'id' => (string) Str::uuid(),
                                    'appointment_id' => $dummyApptId,
                                    'dealership_id' => $dealershipId,
                                    'tech_id' => $tech->id,
                                    'start' => $targetStart->toIso8601String(),
                                    'end' => $targetEnd->toIso8601String(),
                                    'created_at' => $this->now->toIso8601String(),
                                ]);
                            }
                        }

                        return $snapshot;
                    }
                };
            });

            // When: Reschedule X to 14:00 with If-Match "1"
            $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$appointmentXId}/reschedule", [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ], [
                'If-Match' => '"1"',
                'Idempotency-Key' => (string) Str::uuid(),
            ]);

            // Then: 409 NO_AVAILABILITY with reason SLOT_TAKEN
            $rescheduleResponse->assertStatus(409);
            $this->assertSame('NO_AVAILABILITY', $rescheduleResponse->json('code'));
            $this->assertSame('SLOT_TAKEN', $rescheduleResponse->json('reason') ?? $rescheduleResponse->json('extra.reason'));

            // Alternatives present
            $alternatives = $rescheduleResponse->json('alternatives') ?? $rescheduleResponse->json('extra.alternatives');
            $this->assertIsArray($alternatives);
            $this->assertNotEmpty($alternatives);

            // Exactly 3 tries occurred (3 AllocationRetried events dispatched)
            Event::assertDispatchedTimes(AllocationRetried::class, 3);

            // X is unchanged: status, times, technician, bay, version
            /** @var Appointment $rowAfter */
            $rowAfter = Appointment::findOrFail($appointmentXId);
            $this->assertSame($rowBefore->start_at->toIso8601String(), $rowAfter->start_at->toIso8601String());
            $this->assertSame($rowBefore->end_at->toIso8601String(), $rowAfter->end_at->toIso8601String());
            $this->assertSame($rowBefore->technician_id, $rowAfter->technician_id);
            $this->assertSame($rowBefore->bay_id, $rowAfter->bay_id);
            $this->assertSame(1, $rowAfter->version);
            $this->assertSame(AppointmentStatus::CONFIRMED, $rowAfter->status);

            // Allocations unchanged and active
            $allocationsAfter = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentXId)
                ->where('active', true)
                ->orderBy('resource_type')
                ->get()
                ->toArray();
            $this->assertEquals($allocationsBefore, $allocationsAfter);

            // No events or outbox messages written for failed move
            $this->assertSame($eventsCountBefore, DB::table('appointment_events')->where('appointment_id', $appointmentXId)->count());
            $this->assertSame($outboxCountBefore, DB::table('outbox_messages')->where('appointment_id', $appointmentXId)->count());

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }

    /**
     * TC-09-22 (SDD §13 "retry after success"): Reschedule with key K -> 200 (version 2);
     * when resend K with If-Match "1", returns the saved 200 response with Idempotent-Replayed: true header
     * (it must not return 412).
     */
    public function test_retry_after_success_replays(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $slot0900 = '2026-11-03T09:00:00+07:00';
            $slot1400 = '2026-11-03T14:00:00+07:00';

            // 1. Create booking X at 09:00 (version 1)
            $bookResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                'customerId' => $customer->id,
                'vehicleId' => $vehicle->id,
                'serviceTypeId' => $serviceType->id,
                'desiredStart' => $slot0900,
            ], [
                'Idempotency-Key' => (string) Str::uuid(),
            ]);
            $bookResponse->assertStatus(201);
            $appointmentId = $bookResponse->json('id');
            $this->assertSame(1, $bookResponse->json('version'));

            $key = (string) Str::uuid();
            $payload = [
                'newStart' => $slot1400,
                'keepTechnician' => true,
            ];

            // 2. Reschedule with key K and If-Match "1" -> 200, version becomes 2
            $rescheduleResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentId}/reschedule",
                $payload,
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $key,
                ],
            );

            $rescheduleResponse->assertStatus(200);
            $rescheduleResponse->assertHeader('ETag', '"2"');
            $rescheduleResponse->assertHeaderMissing('Idempotent-Replayed');
            $this->assertSame(2, $rescheduleResponse->json('version'));
            $this->assertSame($slot1400, $rescheduleResponse->json('start'));

            /** @var Appointment $fresh */
            $fresh = Appointment::findOrFail($appointmentId);
            $this->assertSame(2, $fresh->version);

            // 3. Resend identical request with key K and OLD If-Match "1"
            // Idempotency runs before If-Match, so it must return the saved 200 with Idempotent-Replayed: true, NOT 412
            $replayResponse = $this->actingAs($advisor)->postJson(
                "/api/v1/appointments/{$appointmentId}/reschedule",
                $payload,
                [
                    'If-Match' => '"1"',
                    'Idempotency-Key' => $key,
                ],
            );

            $replayResponse->assertStatus(200);
            $replayResponse->assertHeader('Idempotent-Replayed', 'true');
            $replayResponse->assertHeader('ETag', '"2"');
            $this->assertSame(2, $replayResponse->json('version'));
            $this->assertSame($slot1400, $replayResponse->json('start'));
            $this->assertSame($appointmentId, $replayResponse->json('id'));

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
