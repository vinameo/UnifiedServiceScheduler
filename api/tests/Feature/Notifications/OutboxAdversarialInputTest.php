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

class OutboxAdversarialInputTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-11-16 (API §B4; Integration):
     * when Each row of the adversarial input table;
     * then As the table.
     */
    public function test_TC_11_16_adversarial_input_table(): void
    {
        // Unauthenticated -> 401
        $this->getJson('/api/v1/outbox')->assertStatus(401);
        $this->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/outbox/some-id/retry')
            ->assertStatus(401);

        $world1 = World::saigonD7();
        $d1 = $world1->dealership;
        $admin1 = $world1->admin();
        $advisor = $world1->advisor();
        $foreman = $world1->foreman();
        $customer = User::factory()->customer()->create();

        $d2 = Dealership::factory()->create([
            'code' => 'DLR-ADVERSARIAL-2',
            'name' => 'Dealership Adversarial Two',
            'timezone' => 'Asia/Ho_Chi_Minh',
        ]);
        $world2 = World::forDealership($d2);

        $now = CarbonImmutable::parse('2026-11-04 14:00:00', new DateTimeZone('Asia/Ho_Chi_Minh'));
        CarbonImmutable::setTestNow($now);

        ['user' => $u1, 'customer' => $c1, 'vehicle' => $v1] = $world1->customerWithVehicle();
        $service1 = $world1->serviceType('OIL_CHANGE');
        $tech1 = $world1->technician('Trần Minh An');
        $bay1 = $world1->bay('K1');

        $appt1 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0020',
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

        ['user' => $u2, 'customer' => $c2, 'vehicle' => $v2] = $world2->customerWithVehicle();
        $service2 = $world2->serviceType('OIL_CHANGE');
        $tech2 = $world2->technician('Trần Minh An');
        $bay2 = $world2->bay('K1');

        $appt2 = Appointment::query()->create([
            'id' => (string) Str::uuid(),
            'reference' => 'APT-20261104-0021',
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

        // D1 messages:
        $d1Pending = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'PENDING',
            'attempts' => 0,
            'next_attempt_at' => $now,
            'created_at' => $now->subMinutes(10),
            'updated_at' => $now->subMinutes(10),
        ]);

        $d1Sent = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'SENT',
            'attempts' => 1,
            'next_attempt_at' => $now->subMinutes(5),
            'sent_at' => $now->subMinutes(5),
            'created_at' => $now->subMinutes(10),
            'updated_at' => $now->subMinutes(5),
        ]);

        $d1Failed = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::DMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now->subMinutes(2),
            'last_error' => 'Webhook returned 500',
            'created_at' => $now->subMinutes(15),
            'updated_at' => $now->subMinutes(2),
        ]);

        // D2 message (FAILED):
        $d2Failed = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d2->id,
            'appointment_id' => $appt2->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::EMAIL,
            'payload' => ['appointmentId' => $appt2->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now->subMinutes(2),
            'last_error' => 'SMTP timeout after 5 s',
            'created_at' => $now->subMinutes(15),
            'updated_at' => $now->subMinutes(2),
        ]);

        // Row 1: 11.1 status=LOST, channel=FAX -> 400
        $res = $this->actingAs($admin1)->getJson('/api/v1/outbox?status=LOST&channel=FAX');
        $res->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $res->json('code'));

        // Row 2: 11.1 appointmentId=abc -> 400
        $res = $this->actingAs($admin1)->getJson('/api/v1/outbox?appointmentId=abc');
        $res->assertStatus(400);
        $this->assertSame('VALIDATION_ERROR', $res->json('code'));

        // Row 3: 11.1 Advisor, foreman, customer -> 403
        $this->actingAs($advisor)->getJson('/api/v1/outbox')->assertStatus(403);
        $this->actingAs($foreman)->getJson('/api/v1/outbox')->assertStatus(403);
        $this->actingAs($customer)->getJson('/api/v1/outbox')->assertStatus(403);

        // 11.3 Advisor, foreman, customer -> 403
        $this->actingAs($advisor)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Failed->id}/retry")
            ->assertStatus(403);

        $this->actingAs($foreman)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Failed->id}/retry")
            ->assertStatus(403);

        $this->actingAs($customer)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Failed->id}/retry")
            ->assertStatus(403);

        // Row 4: 11.3 {message} of another dealership / missing / abc -> 404 OUTBOX_MESSAGE_NOT_FOUND
        // 4a. Another dealership's message id
        $res = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d2Failed->id}/retry");
        $res->assertStatus(404);
        $this->assertSame('OUTBOX_MESSAGE_NOT_FOUND', $res->json('code'));

        // 4b. Missing message id (random uuid)
        $missingUuid = (string) Str::uuid();
        $res = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$missingUuid}/retry");
        $res->assertStatus(404);
        $this->assertSame('OUTBOX_MESSAGE_NOT_FOUND', $res->json('code'));

        // 4c. Malformed id 'abc'
        $res = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson('/api/v1/outbox/abc/retry');
        $res->assertStatus(404);
        $this->assertSame('OUTBOX_MESSAGE_NOT_FOUND', $res->json('code'));

        // Row 5: 11.3 A SENT or PENDING message -> 422 OUTBOX_NOT_RETRYABLE
        // 5a. SENT message
        $res = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Sent->id}/retry");
        $res->assertStatus(422);
        $this->assertSame('OUTBOX_NOT_RETRYABLE', $res->json('code'));

        // 5b. PENDING message
        $res = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Pending->id}/retry");
        $res->assertStatus(422);
        $this->assertSame('OUTBOX_NOT_RETRYABLE', $res->json('code'));

        // Row 6: 11.3 No Idempotency-Key -> 400 IDEMPOTENCY_KEY_REQUIRED
        $this->flushHeaders();
        $res = $this->actingAs($admin1)->postJson("/api/v1/outbox/{$d1Failed->id}/retry");
        $res->assertStatus(400);
        $this->assertSame('IDEMPOTENCY_KEY_REQUIRED', $res->json('code'));

        // Row 7: 11.3 Same key twice -> The saved 200; one reset only
        $key7 = (string) Str::uuid();
        $res1 = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => $key7])
            ->postJson("/api/v1/outbox/{$d1Failed->id}/retry");
        $res1->assertStatus(200);
        $this->assertSame('PENDING', $res1->json('data.status'));
        $this->assertSame(0, $res1->json('data.attempts'));

        // Replay with same key
        $res2 = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => $key7])
            ->postJson("/api/v1/outbox/{$d1Failed->id}/retry");
        $res2->assertStatus(200);
        $this->assertSame($res1->json(), $res2->json());

        // Row 8: 11.3 Body { "attempts": -5 } -> Ignored
        // Create another FAILED message to test body ignoring on retry
        $d1Failed2 = OutboxMessage::query()->create([
            'id' => (string) Str::uuid(),
            'event_id' => (string) Str::uuid(),
            'dealership_id' => $d1->id,
            'appointment_id' => $appt1->id,
            'event_type' => OutboxEventType::AppointmentConfirmed,
            'channel' => OutboxChannel::SMS,
            'payload' => ['appointmentId' => $appt1->id],
            'status' => 'FAILED',
            'attempts' => 5,
            'next_attempt_at' => $now->subMinutes(2),
            'last_error' => 'Carrier rejection',
            'created_at' => $now->subMinutes(15),
            'updated_at' => $now->subMinutes(2),
        ]);

        $res8 = $this->actingAs($admin1)
            ->withHeaders(['Idempotency-Key' => (string) Str::uuid()])
            ->postJson("/api/v1/outbox/{$d1Failed2->id}/retry", [
                'attempts' => -5,
                'status' => 'SENT',
                'extraField' => 'ignored',
            ]);
        $res8->assertStatus(200);
        $this->assertSame('PENDING', $res8->json('data.status'));
        $this->assertSame(0, $res8->json('data.attempts'));
        $d1Failed2->refresh();
        $this->assertSame(0, $d1Failed2->attempts);
        $this->assertSame('PENDING', $d1Failed2->status);
    }
}
