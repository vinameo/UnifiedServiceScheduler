<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
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
}
