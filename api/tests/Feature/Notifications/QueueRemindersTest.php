<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Services\OutboxRelayInterface;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Notifications\RecordingChannel;
use Tests\Support\World;
use Tests\TestCase;

class QueueRemindersTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-11-10 (AC-11.8, INV-11.7; Integration):
     * given Confirmed booking 24 h ahead, opt-in; another with opt-out; another 30 h ahead;
     * when outbox:queue-reminders twice;
     * then Exactly 1 AppointmentReminder SMS row (the opted-in, 24 h one).
     */
    public function test_AC_11_8_one_reminder_per_booking(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        ['user' => $u1, 'customer' => $c1, 'vehicle' => $v1] = $world->customerWithVehicle();
        $c1->update(['phone' => '0901234567']);

        ['user' => $u2, 'customer' => $c2, 'vehicle' => $v2] = $world->customerWithVehicle();
        $c2->update(['phone' => '0901234568']);

        ['user' => $u3, 'customer' => $c3, 'vehicle' => $v3] = $world->customerWithVehicle();
        $c3->update(['phone' => '0901234569']);

        $service = $world->serviceType('OIL_CHANGE');

        $t0 = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($t0);

        // Booking 1: 24h ahead (2026-11-03 08:00), reminder_opt_in = true
        $b1Start = $t0->addHours(24)->toIso8601String();
        $b1Response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c1->id,
            'vehicleId' => $v1->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $b1Start,
            'reminderOptIn' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $b1Response->assertStatus(201);
        $b1Id = (string) $b1Response->json('id');
        $b1Ref = (string) $b1Response->json('reference');

        // Booking 2: 24h ahead (2026-11-03 08:30), reminder_opt_in = false (opt-out)
        $b2Start = $t0->addHours(24)->addMinutes(30)->toIso8601String();
        $b2Response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c2->id,
            'vehicleId' => $v2->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $b2Start,
            'reminderOptIn' => false,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $b2Response->assertStatus(201);

        // Booking 3: 30h ahead (2026-11-03 14:00), reminder_opt_in = true (outside [now + 23h, now + 25h))
        $b3Start = $t0->addHours(30)->toIso8601String();
        $b3Response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $c3->id,
            'vehicleId' => $v3->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $b3Start,
            'reminderOptIn' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $b3Response->assertStatus(201);

        // Precondition: No reminder rows exist before running queue-reminders
        $this->assertSame(0, DB::table('outbox_messages')
            ->where('event_type', OutboxEventType::AppointmentReminder->value)
            ->count());

        // Run outbox:queue-reminders twice at t0
        $exit1 = Artisan::call('outbox:queue-reminders', ['--now' => $t0->toIso8601String()]);
        $this->assertSame(0, $exit1);

        $exit2 = Artisan::call('outbox:queue-reminders', ['--now' => $t0->toIso8601String()]);
        $this->assertSame(0, $exit2);

        // Then: Exactly 1 AppointmentReminder SMS row (the opted-in, 24 h one)
        $reminderRows = DB::table('outbox_messages')
            ->where('event_type', OutboxEventType::AppointmentReminder->value)
            ->get();

        $this->assertCount(1, $reminderRows);

        $reminder = $reminderRows->first();
        $this->assertNotNull($reminder);
        $this->assertSame($b1Id, $reminder->appointment_id);
        $this->assertSame($world->dealership->id, $reminder->dealership_id);
        $this->assertSame(OutboxChannel::SMS->value, $reminder->channel);
        $this->assertSame('PENDING', $reminder->status);
        $this->assertSame(0, $reminder->attempts);

        $expectedStartIso = CarbonImmutable::parse($b1Start)->utc()->toIso8601String();
        $expectedDedupeKey = "reminder:{$b1Id}:{$expectedStartIso}";
        $this->assertSame($expectedDedupeKey, $reminder->dedupe_key);

        /** @var array<string, mixed> $payload */
        $payload = json_decode((string) $reminder->payload, true);
        $this->assertSame($b1Id, $payload['appointmentId']);
        $this->assertSame($b1Ref, $payload['reference']);
        $this->assertSame($c1->id, $payload['customerId']);
        $this->assertSame($expectedStartIso, $payload['start']);
        $this->assertNotNull($payload['end']);
    }

    /**
     * TC-11-11 (D-11.6, D-11.7; Integration):
     * given A reminder queued; the booking rescheduled to another day;
     * when runOnce; queue-reminders later for the new time;
     * then The old reminder SKIPPED; a new reminder row for the new start (new dedupe_key).
     */
    public function test_rescheduled_booking_gets_a_fresh_reminder(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $t0 = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($t0);

        // Initial confirmed booking for 24h ahead (2026-11-03 08:00)
        $origStart = $t0->addHours(24);
        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $origStart->toIso8601String(),
            'reminderOptIn' => true,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $bookingResponse->assertStatus(201);
        $apptId = (string) $bookingResponse->json('id');

        // Setup fake channels
        $smsFake = new RecordingChannel;
        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $smsFake);
        Mail::fake();

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);

        // Relay sends initial confirmation messages
        $relay->runOnce($t0);

        // Queue reminder at t0
        $exit1 = Artisan::call('outbox:queue-reminders', ['--now' => $t0->toIso8601String()]);
        $this->assertSame(0, $exit1);

        $initialReminders = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentReminder->value)
            ->get();
        $this->assertCount(1, $initialReminders);
        $initialReminder = $initialReminders->first();
        $this->assertNotNull($initialReminder);
        $oldDedupeKey = "reminder:{$apptId}:".$origStart->utc()->toIso8601String();
        $this->assertSame($oldDedupeKey, $initialReminder->dedupe_key);
        $this->assertSame('PENDING', $initialReminder->status);

        // Reschedule the booking to another day (e.g. 2026-11-05 09:00:00)
        $newStart = CarbonImmutable::parse('2026-11-05 09:00:00', 'Asia/Ho_Chi_Minh');
        $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$apptId}/reschedule", [
            'newStart' => $newStart->toIso8601String(),
            'keepTechnician' => true,
        ], [
            'If-Match' => '"1"',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $rescheduleResponse->assertStatus(200);

        // When relay runOnce runs:
        // The old reminder is SKIPPED because its payload start differs from current appointment start_at
        $relayResult = $relay->runOnce($t0);
        $this->assertGreaterThanOrEqual(1, $relayResult->skipped);

        $oldReminder = DB::table('outbox_messages')
            ->where('id', $initialReminder->id)
            ->first();
        $this->assertNotNull($oldReminder);
        $this->assertSame('SKIPPED', $oldReminder->status);
        $this->assertSame('START_TIME_CHANGED', $oldReminder->last_error);
        $this->assertNull($oldReminder->sent_at);

        // Advance time to 24 h before the new booking time (2026-11-04 09:00:00)
        $t1 = $newStart->subHours(24);
        CarbonImmutable::setTestNow($t1);

        // Run queue-reminders for the new time
        $exit2 = Artisan::call('outbox:queue-reminders', ['--now' => $t1->toIso8601String()]);
        $this->assertSame(0, $exit2);

        // Then: The old reminder is SKIPPED; a new reminder row exists for the new start (new dedupe_key)
        $allReminders = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentReminder->value)
            ->orderBy('created_at', 'asc')
            ->get();

        $this->assertCount(2, $allReminders);

        $firstReminder = $allReminders[0] ?? null;
        $this->assertNotNull($firstReminder);
        $this->assertSame('SKIPPED', $firstReminder->status);
        $this->assertSame('START_TIME_CHANGED', $firstReminder->last_error);
        $this->assertSame($oldDedupeKey, $firstReminder->dedupe_key);

        $newReminder = $allReminders[1] ?? null;
        $this->assertNotNull($newReminder);
        $this->assertSame('PENDING', $newReminder->status);
        $newExpectedDedupeKey = "reminder:{$apptId}:".$newStart->utc()->toIso8601String();
        $this->assertSame($newExpectedDedupeKey, $newReminder->dedupe_key);
        $this->assertSame(0, $newReminder->attempts);
        $this->assertNull($newReminder->sent_at);

        /** @var array<string, mixed> $newPayload */
        $newPayload = json_decode((string) $newReminder->payload, true);
        $this->assertSame($newStart->utc()->toIso8601String(), $payloadStart = $newPayload['start']);
    }

    /**
     * Test outbox:queue-reminders is registered in the console schedule hourly.
     */
    public function test_schedule_contains_queue_reminders_hourly(): void
    {
        /** @var Schedule $schedule */
        $schedule = app(Schedule::class);
        $events = collect($schedule->events());

        $reminderEvent = $events->first(function (Event $event) {
            return str_contains((string) $event->command, 'outbox:queue-reminders');
        });

        $this->assertNotNull($reminderEvent, 'outbox:queue-reminders must be registered in routes/console.php');
        $this->assertSame('0 * * * *', $reminderEvent->expression);
    }
}
