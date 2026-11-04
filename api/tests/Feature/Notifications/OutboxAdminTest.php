<?php

namespace Tests\Feature\Notifications;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use App\Models\Dealership;
use App\Models\OutboxMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\World;
use Tests\TestCase;

class OutboxAdminTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-11-12 (AC-11.9, INV-11.8; Integration):
     * given Rows of D1 and D2; admin of D1;
     * when List; summary;
     * then Only D1 rows; counts by state (retrying = PENDING with attempts > 0).
     */
    public function test_AC_11_9_admin_sees_own_dealership(): void
    {
        $world1 = World::saigonD7();
        $d1 = $world1->dealership;
        $admin1 = $world1->admin();

        $d2 = Dealership::factory()->create([
            'code' => 'DLR-D2',
            'name' => 'Dealership Two',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $world2 = World::forDealership($d2);
        $admin2 = $world2->admin();

        ['user' => $u1, 'customer' => $c1, 'vehicle' => $v1] = $world1->customerWithVehicle();
        $service1 = $world1->serviceType('OIL_CHANGE');
        $tech1 = $world1->technician('Trần Minh An');
        $bay1 = $world1->bay('K1');

        ['user' => $u2, 'customer' => $c2, 'vehicle' => $v2] = $world2->customerWithVehicle();
        $service2 = $world2->serviceType('OIL_CHANGE');
        $tech2 = $world2->technician('Trần Minh An');
        $bay2 = $world2->bay('K1');

        $now = CarbonImmutable::parse('2026-11-04 14:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow($now);

        $appt1 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0001',
            'dealership_id' => $d1->id,
            'customer_id' => $c1->id,
            'vehicle_id' => $v1->id,
            'service_type_id' => $service1->id,
            'technician_id' => $tech1->id,
            'bay_id' => $bay1->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => $now->addDay(),
            'end_at' => $now->addDay()->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $u1->id,
        ]);

        $appt2 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0002',
            'dealership_id' => $d2->id,
            'customer_id' => $c2->id,
            'vehicle_id' => $v2->id,
            'service_type_id' => $service2->id,
            'technician_id' => $tech2->id,
            'bay_id' => $bay2->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => $now->addDay(),
            'end_at' => $now->addDay()->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $u2->id,
        ]);

        // D1 messages: 6 total
        // 1. PENDING (attempts = 0)
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(10),
            'updated_at' => $now->subMinutes(10),
        ]);

        // 2. RETRYING (PENDING with attempts = 2)
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'PENDING',
            'attempts' => 2,
            'next_attempt_at' => $now->addSeconds(40),
            'last_error' => 'Connection timeout',
            'created_at' => $now->subMinutes(8),
            'updated_at' => $now->subMinutes(8),
        ]);

        // 3. FAILED (attempts = 5)
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentCancelled,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now,
            'last_error' => 'SMTP timeout after 5 s',
            'created_at' => $now->subMinutes(6),
            'updated_at' => $now->subMinutes(6),
        ]);

        // 4. SENT today
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::DMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'SENT',
            'attempts' => 1,
            'next_attempt_at' => $now,
            'sent_at' => $now->subMinutes(3),
            'created_at' => $now->subMinutes(4),
            'updated_at' => $now->subMinutes(3),
        ]);

        // 5. SENT yesterday (should NOT be counted in sentToday)
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'SENT',
            'attempts' => 1,
            'next_attempt_at' => $now->subDays(1),
            'sent_at' => $now->subDays(1),
            'created_at' => $now->subDays(1),
            'updated_at' => $now->subDays(1),
        ]);

        // 6. SKIPPED
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentReminder,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'SKIPPED',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'last_error' => 'ALREADY_CANCELLED',
            'created_at' => $now->subMinutes(2),
            'updated_at' => $now->subMinutes(2),
        ]);

        // D2 messages: 4 total
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d2->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(5),
            'updated_at' => $now->subMinutes(5),
        ]);

        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d2->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'PENDING',
            'attempts' => 1,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(5),
            'updated_at' => $now->subMinutes(5),
        ]);

        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d2->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentCancelled,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now,
            'last_error' => 'Connection refused',
            'created_at' => $now->subMinutes(4),
            'updated_at' => $now->subMinutes(4),
        ]);

        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d2->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::DMS,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'SENT',
            'attempts' => 1,
            'next_attempt_at' => $now,
            'sent_at' => $now->subMinutes(1),
            'created_at' => $now->subMinutes(2),
            'updated_at' => $now->subMinutes(1),
        ]);

        // When: D1 Admin lists outbox
        $listResponse = $this->actingAs($admin1)->getJson('/api/v1/outbox');
        $listResponse->assertStatus(200);

        $items = $listResponse->json('data');
        $this->assertCount(6, $items, 'Admin D1 must only see their own 6 messages');
        $this->assertSame(6, $listResponse->json('meta.total'));

        // Check response structure and customerName presence (phone/email absent)
        $firstItem = $items[0];
        $this->assertArrayHasKey('id', $firstItem);
        $this->assertArrayHasKey('eventId', $firstItem);
        $this->assertArrayHasKey('eventType', $firstItem);
        $this->assertArrayHasKey('channel', $firstItem);
        $this->assertArrayHasKey('status', $firstItem);
        $this->assertArrayHasKey('displayStatus', $firstItem);
        $this->assertArrayHasKey('attempts', $firstItem);
        $this->assertArrayHasKey('nextAttemptAt', $firstItem);
        $this->assertArrayHasKey('lastError', $firstItem);
        $this->assertArrayHasKey('sentAt', $firstItem);
        $this->assertArrayHasKey('createdAt', $firstItem);
        $this->assertArrayHasKey('appointment', $firstItem);

        $this->assertArrayNotHasKey('phone', $firstItem);
        $this->assertArrayNotHasKey('email', $firstItem);
        $this->assertArrayNotHasKey('payload', $firstItem);

        // Validate appointment details
        $this->assertSame($appt1->id, $firstItem['appointment']['id']);
        $this->assertSame($appt1->reference, $firstItem['appointment']['reference']);
        $this->assertSame($c1->full_name, $firstItem['appointment']['customerName']);

        // Check display statuses in D1 list
        $displayStatuses = array_column($items, 'displayStatus');
        $this->assertContains('Pending', $displayStatuses);
        $this->assertContains('Retrying', $displayStatuses);
        $this->assertContains('Failed', $displayStatuses);
        $this->assertContains('Sent', $displayStatuses);
        $this->assertContains('Skipped', $displayStatuses);

        // When: D1 Admin gets summary
        $summaryResponse = $this->actingAs($admin1)->getJson('/api/v1/outbox/summary');
        $summaryResponse->assertStatus(200);
        $summaryResponse->assertExactJson([
            'data' => [
                'pending' => 1,
                'retrying' => 1,
                'failed' => 1,
                'sentToday' => 1,
            ],
        ]);

        // When: D2 Admin gets summary
        $summary2Response = $this->actingAs($admin2)->getJson('/api/v1/outbox/summary');
        $summary2Response->assertStatus(200);
        $summary2Response->assertExactJson([
            'data' => [
                'pending' => 1,
                'retrying' => 1,
                'failed' => 1,
                'sentToday' => 1,
            ],
        ]);
    }

    /**
     * Test outbox list filtering by status, channel, eventType, and appointmentId.
     */
    public function test_outbox_list_filters(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $admin = $world->admin();

        ['user' => $u1, 'customer' => $c1, 'vehicle' => $v1] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');
        $tech = $world->technician('Trần Minh An');
        $bay = $world->bay('K1');

        $now = CarbonImmutable::parse('2026-11-04 10:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow($now);

        $appt1 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0010',
            'dealership_id' => $dealership->id,
            'customer_id' => $c1->id,
            'vehicle_id' => $v1->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED,
            'start_at' => $now->addDay(),
            'end_at' => $now->addDay()->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $u1->id,
        ]);

        $appt2 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0011',
            'dealership_id' => $dealership->id,
            'customer_id' => $c1->id,
            'vehicle_id' => $v1->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CANCELLED,
            'start_at' => $now->addDays(2),
            'end_at' => $now->addDays(2)->addHour(),
            'version' => 2,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => false,
            'created_by_user_id' => $u1->id,
        ]);

        // Message 1: PENDING, attempts 0, SMS, AppointmentConfirmed, Appt1
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $dealership->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(5),
            'updated_at' => $now->subMinutes(5),
        ]);

        // Message 2: PENDING, attempts 2 (RETRYING), EMAIL, AppointmentCancelled, Appt2
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $dealership->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentCancelled,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'PENDING',
            'attempts' => 2,
            'next_attempt_at' => $now->addSeconds(30),
            'created_at' => $now->subMinutes(4),
            'updated_at' => $now->subMinutes(4),
        ]);

        // Message 3: FAILED, attempts 5, DMS, AppointmentRescheduled, Appt1
        OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $dealership->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentRescheduled,
            'channel' => OutboxChannel::DMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(3),
            'updated_at' => $now->subMinutes(3),
        ]);

        // Test status=PENDING (attempts = 0)
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?status=PENDING');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Pending', $res->json('data.0.displayStatus'));

        // Test status=RETRYING (attempts > 0)
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?status=RETRYING');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Retrying', $res->json('data.0.displayStatus'));

        // Test status=FAILED
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?status=FAILED');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('Failed', $res->json('data.0.displayStatus'));

        // Test channel=SMS
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?channel=SMS');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('SMS', $res->json('data.0.channel'));

        // Test channel=EMAIL
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?channel=EMAIL');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('EMAIL', $res->json('data.0.channel'));

        // Test eventType=AppointmentCancelled
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?eventType=AppointmentCancelled');
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));
        $this->assertSame('AppointmentCancelled', $res->json('data.0.eventType'));

        // Test appointmentId
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?appointmentId='.$appt1->id);
        $res->assertStatus(200);
        $this->assertCount(2, $res->json('data'));

        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?appointmentId='.$appt2->id);
        $res->assertStatus(200);
        $this->assertCount(1, $res->json('data'));

        // Foreign appointment UUID
        $otherUuid = (string) Str::uuid();
        $res = $this->actingAs($admin)->getJson('/api/v1/outbox?appointmentId='.$otherUuid);
        $res->assertStatus(200);
        $this->assertCount(0, $res->json('data'));
    }

    /**
     * Test cursor pagination on outbox list.
     */
    public function test_outbox_list_cursor_pagination(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $admin = $world->admin();

        $now = CarbonImmutable::parse('2026-11-04 12:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow($now);

        // Create 5 outbox messages with distinct timestamps
        for ($i = 0; $i < 5; $i++) {
            OutboxMessage::query()->create([
                'id' => (string) Str::uuid(),
                'event_id' => (string) Str::uuid(),
                'dealership_id' => $dealership->id,
                'appointment_id' => null,
                'event_type' => OutboxEventType::AppointmentConfirmed,
                'channel' => OutboxChannel::EMAIL,
                'payload' => ['index' => $i],
                'status' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => $now,
                'created_at' => $now->subMinutes(10 - $i),
                'updated_at' => $now->subMinutes(10 - $i),
            ]);
        }

        // Page 1: limit 2
        $page1 = $this->actingAs($admin)->getJson('/api/v1/outbox?limit=2');
        $page1->assertStatus(200);
        $this->assertCount(2, $page1->json('data'));
        $this->assertSame(5, $page1->json('meta.total'));
        $cursor1 = $page1->json('meta.nextCursor');
        $this->assertNotNull($cursor1);

        // Page 2: limit 2 with cursor
        $page2 = $this->actingAs($admin)->getJson('/api/v1/outbox?limit=2&cursor='.$cursor1);
        $page2->assertStatus(200);
        $this->assertCount(2, $page2->json('data'));
        $cursor2 = $page2->json('meta.nextCursor');
        $this->assertNotNull($cursor2);

        // Ensure page 1 and page 2 have different items
        $page1Ids = array_column($page1->json('data'), 'id');
        $page2Ids = array_column($page2->json('data'), 'id');
        $this->assertEmpty(array_intersect($page1Ids, $page2Ids));

        // Page 3: limit 2 with cursor2 (last item)
        $page3 = $this->actingAs($admin)->getJson('/api/v1/outbox?limit=2&cursor='.$cursor2);
        $page3->assertStatus(200);
        $this->assertCount(1, $page3->json('data'));
        $this->assertNull($page3->json('meta.nextCursor'));
    }

    /**
     * Adversarial input tests for API-11.1 (GET /api/v1/outbox).
     */
    public function test_adversarial_11_1_list_inputs(): void
    {
        // 1. Unauthenticated -> 401
        $this->getJson('/api/v1/outbox')->assertStatus(401);

        $world = World::saigonD7();
        $admin = $world->admin();
        $advisor = $world->advisor();
        $foreman = $world->foreman();
        $customer = User::factory()->customer()->create();

        // 2. Non-admin roles -> 403
        $this->actingAs($advisor)->getJson('/api/v1/outbox')->assertStatus(403);
        $this->actingAs($foreman)->getJson('/api/v1/outbox')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/v1/outbox')->assertStatus(403);

        // 3. Invalid status -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?status=LOST')->assertStatus(400);

        // 4. Invalid channel -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?channel=FAX')->assertStatus(400);

        // 5. Invalid eventType -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?eventType=NonExistentEvent')->assertStatus(400);

        // 6. Invalid appointmentId (not a uuid) -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?appointmentId=abc')->assertStatus(400);

        // 7. Invalid limit (< 1 or > 200) -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?limit=0')->assertStatus(400);
        $this->actingAs($admin)->getJson('/api/v1/outbox?limit=201')->assertStatus(400);

        // 8. Invalid cursor -> 400
        $this->actingAs($admin)->getJson('/api/v1/outbox?cursor=invalid_base64_cursor!')->assertStatus(400);

        // 9. All query parameters blank -> 200 (treated as omitted)
        $this->actingAs($admin)->getJson('/api/v1/outbox?status=&channel=&eventType=&appointmentId=&cursor=&limit=')->assertStatus(200);
    }

    /**
     * Adversarial input tests for API-11.2 (GET /api/v1/outbox/summary).
     */
    public function test_adversarial_11_2_summary_inputs(): void
    {
        // 1. Unauthenticated -> 401
        $this->getJson('/api/v1/outbox/summary')->assertStatus(401);

        $world = World::saigonD7();
        $advisor = $world->advisor();
        $foreman = $world->foreman();
        $customer = User::factory()->customer()->create();

        // 2. Non-admin roles -> 403
        $this->actingAs($advisor)->getJson('/api/v1/outbox/summary')->assertStatus(403);
        $this->actingAs($foreman)->getJson('/api/v1/outbox/summary')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/v1/outbox/summary')->assertStatus(403);
    }
}
