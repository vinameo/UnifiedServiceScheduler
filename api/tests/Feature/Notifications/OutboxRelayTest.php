<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\Channels\FailingChannel;
use App\Domain\Notifications\Channels\NotificationChannel;
use App\Domain\Notifications\Mail\AppointmentNotification;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\OutboxEventType;
use App\Domain\Notifications\Recipient;
use App\Domain\Notifications\Services\OutboxRelayInterface;
use App\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Error;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Logging\CapturesLogs;
use Tests\Support\Notifications\RecordingChannel;
use Tests\Support\World;
use Tests\TestCase;

class OutboxRelayTest extends TestCase
{
    use AssertsNoOverlap;
    use CapturesLogs;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCapturesLogs();
    }

    /**
     * TC-11-01 (AC-11.5, AC-11.1; Integration):
     * given A confirmed booking → 3 PENDING rows; recording fake channels; Mail::fake();
     * when runOnce(now);
     * then 3 rows SENT, sent_at = now, attempts 1; the SMS fake got the customer's phone;
     *      the mailable went to the account email; log lines have no phone/email.
     */
    public function test_AC_11_5_relay_sends_all_channels(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $bookingNow = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($bookingNow);

        $desiredStart = '2026-11-02T09:00:00+07:00';
        $key = (string) Str::uuid();

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $desiredStart,
        ], [
            'Idempotency-Key' => $key,
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Precondition: 3 PENDING rows created by booking
        $initialRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->get();
        $this->assertCount(3, $initialRows);
        foreach ($initialRows as $row) {
            $this->assertSame('PENDING', $row->status);
            $this->assertSame(0, $row->attempts);
            $this->assertNull($row->sent_at);
        }

        // Set up recording fake channels for SMS & DMS, Mail::fake() for EMAIL
        $smsFake = new RecordingChannel;
        $dmsFake = new RecordingChannel;

        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $smsFake);
        $registry->register(OutboxChannel::DMS, $dmsFake);

        Mail::fake();

        $relayNow = CarbonImmutable::parse('2026-11-02 08:00:10', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($relayNow);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);
        $result = $relay->runOnce($relayNow);

        $this->assertSame(3, $result->claimed);
        $this->assertSame(3, $result->sent);
        $this->assertSame(0, $result->failed);
        $this->assertSame(0, $result->skipped);

        // Assert 3 rows SENT with sent_at = now and attempts = 1
        $sentRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->get();

        $this->assertCount(3, $sentRows);
        foreach ($sentRows as $row) {
            $this->assertSame('SENT', $row->status);
            $this->assertSame(1, $row->attempts);
            $this->assertNotNull($row->sent_at);
            $this->assertSame(
                $relayNow->utc()->toIso8601String(),
                CarbonImmutable::parse($row->sent_at)->utc()->toIso8601String()
            );
        }

        // Assert SMS fake got the customer's phone
        $this->assertSame(1, $smsFake->count());
        $smsRecipient = $smsFake->lastRecipient();
        $this->assertNotNull($smsRecipient);
        $this->assertSame('0901234567', $smsRecipient->phone);

        // Assert DMS fake received the message
        $this->assertSame(1, $dmsFake->count());

        // Assert mailable went to the account email
        Mail::assertSent(AppointmentNotification::class, function (AppointmentNotification $mail) use ($user) {
            return $mail->hasTo((string) $user->email);
        });

        // Assert log lines have no phone or email
        $records = $this->testLogHandler->getRecords();
        $this->assertNotEmpty($records);

        foreach ($records as $record) {
            $recordString = json_encode([
                'message' => $record->message,
                'context' => $record->context,
                'extra' => $record->extra,
            ]);

            $this->assertStringNotContainsString('0901234567', (string) $recordString);
            $this->assertStringNotContainsString((string) $user->email, (string) $recordString);
        }
    }

    /**
     * TC-11-02 (AC-11.2, BR-11.1; Integration):
     * given Every channel bound to FailingChannel;
     * when POST /appointments;
     * then 201; response time comparable to normal (no channel call); rows PENDING.
     */
    public function test_AC_11_2_failing_channels_do_not_affect_booking(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        // Bind every channel to FailingChannel
        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $failing = new FailingChannel;
        $registry->register(OutboxChannel::SMS, $failing);
        $registry->register(OutboxChannel::EMAIL, $failing);
        $registry->register(OutboxChannel::DMS, $failing);

        $key = (string) Str::uuid();
        $desiredStart = '2026-11-02T09:00:00+07:00';

        $startTime = microtime(true);
        $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $desiredStart,
        ], [
            'Idempotency-Key' => $key,
        ]);
        $duration = microtime(true) - $startTime;

        $response->assertStatus(201);
        $this->assertLessThan(2.0, $duration, 'Booking response time should be fast and unaffected by channel failures');

        $apptId = $response->json('id');
        $this->assertNotNull($apptId);

        // Outbox rows remain PENDING and untouched by booking request
        $outboxRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->get();

        $this->assertCount(3, $outboxRows);
        foreach ($outboxRows as $row) {
            $this->assertSame('PENDING', $row->status);
            $this->assertSame(0, $row->attempts);
            $this->assertNull($row->sent_at);
            $this->assertNull($row->last_error);
        }
    }

    /**
     * Test outbox:relay artisan command runs successfully.
     */
    public function test_command_outbox_relay_runs_successfully(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC');

        $exitCode = Artisan::call('outbox:relay', [
            '--now' => $now->toIso8601String(),
            '--batch' => 10,
        ]);

        $this->assertSame(0, $exitCode);
    }

    /**
     * TC-11-06 (D-11.5, INV-11.5; Integration):
     * given A DMS row; fake HTTP (Http::fake) with DMS_WEBHOOK_URL set;
     * when runOnce;
     * then The request has header Idempotency-Key: {eventId}:DMS and body with eventId;
     *      the stored payload has no phone/email keys.
     * Test: …::test_D_11_5_messages_carry_event_id.
     */
    public function test_D_11_5_messages_carry_event_id(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $bookingNow = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($bookingNow);

        $desiredStart = '2026-11-02T09:00:00+07:00';
        $key = (string) Str::uuid();

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $desiredStart,
        ], [
            'Idempotency-Key' => $key,
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Precondition: A DMS row exists
        $dmsRow = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('channel', 'DMS')
            ->first();
        $this->assertNotNull($dmsRow);
        $this->assertSame('PENDING', $dmsRow->status);

        // Assert stored payload has no phone/email keys (INV-11.5)
        /** @var array<string, mixed> $initialPayload */
        $initialPayload = json_decode((string) $dmsRow->payload, true);
        $this->assertArrayNotHasKey('phone', $initialPayload);
        $this->assertArrayNotHasKey('email', $initialPayload);

        // Configure fake HTTP and DMS_WEBHOOK_URL
        $webhookUrl = 'https://dms.example.test/incoming-events';
        config([
            'outbox.dms.driver' => 'webhook',
            'outbox.dms.webhook_url' => $webhookUrl,
        ]);

        Http::fake([
            $webhookUrl => Http::response(['status' => 'received'], 200),
        ]);

        // Register fake channels for SMS & EMAIL to isolate DMS webhook testing
        $smsFake = new RecordingChannel;
        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->forget(OutboxChannel::DMS);
        $registry->register(OutboxChannel::SMS, $smsFake);
        Mail::fake();

        $relayNow = CarbonImmutable::parse('2026-11-02 08:00:10', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($relayNow);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);
        $result = $relay->runOnce($relayNow);

        $this->assertGreaterThanOrEqual(1, $result->sent);

        // Verify HTTP request sent to DMS_WEBHOOK_URL
        $eventId = (string) $dmsRow->event_id;
        Http::assertSent(function (Request $request) use ($webhookUrl, $eventId) {
            $this->assertSame($webhookUrl, $request->url());
            $this->assertTrue($request->hasHeader('Idempotency-Key', "{$eventId}:DMS"));

            $data = $request->data();
            $this->assertSame($eventId, $data['eventId']);
            $this->assertArrayNotHasKey('phone', $data);
            $this->assertArrayNotHasKey('email', $data);

            $raw = $request->body();
            $this->assertStringNotContainsString('0901234567', $raw);

            return true;
        });

        // Verify DMS row marked SENT
        $updatedDmsRow = DB::table('outbox_messages')
            ->where('id', $dmsRow->id)
            ->first();
        $this->assertNotNull($updatedDmsRow);
        $this->assertSame('SENT', $updatedDmsRow->status);
        $this->assertSame(1, $updatedDmsRow->attempts);
        $this->assertNotNull($updatedDmsRow->sent_at);

        // Verify the stored payload in database still has no phone/email keys
        /** @var array<string, mixed> $updatedPayload */
        $updatedPayload = json_decode((string) $updatedDmsRow->payload, true);
        $this->assertArrayNotHasKey('phone', $updatedPayload);
        $this->assertArrayNotHasKey('email', $updatedPayload);
    }

    /**
     * TC-11-04 (AC-11.6, INV-11.4; Integration):
     * given One SMS row; FailingChannel;
     * when runOnce at t, then travel to each next_attempt_at and run again, 5 times;
     * then attempts 1…5; next_attempt_at gaps 10/20/40/80 s;
     *      after the 5th: FAILED, last_error set (redacted), 1 api_events row DELIVERY_FAILED.
     */
    public function test_AC_11_6_backoff_then_failed(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $t0 = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($t0);

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => '2026-11-02T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Keep only one SMS row for this test
        DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('channel', '!=', 'SMS')
            ->delete();

        $smsRow = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('channel', 'SMS')
            ->first();
        $this->assertNotNull($smsRow);
        $this->assertSame(0, $smsRow->attempts);
        $this->assertSame('PENDING', $smsRow->status);

        // Configure FailingChannel with sensitive data to verify redaction and length limit <= 500 chars
        $sensitiveErrorMessage = 'SMS provider gateway error for 0901234567 / test.user@example.com: '.str_repeat('X', 600);
        $failingChannel = new FailingChannel($sensitiveErrorMessage);

        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $failingChannel);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);

        $expectedGaps = [10, 20, 40, 80]; // seconds: 10 * 2^(attempts - 1)
        $currentTime = $t0;

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $result = $relay->runOnce($currentTime);

            $this->assertSame(1, $result->claimed);
            $this->assertSame(0, $result->sent);
            $this->assertSame(1, $result->failed);

            $row = DB::table('outbox_messages')->where('id', $smsRow->id)->first();
            $this->assertNotNull($row);
            $this->assertSame($attempt, $row->attempts);

            if ($attempt < 5) {
                $this->assertSame('PENDING', $row->status);
                $expectedGap = $expectedGaps[$attempt - 1];
                $nextAttemptAt = CarbonImmutable::parse($row->next_attempt_at);
                $actualGap = (int) $currentTime->diffInSeconds($nextAttemptAt);
                $this->assertSame($expectedGap, $actualGap, "Attempt {$attempt} should wait {$expectedGap}s");

                // api_events row DELIVERY_FAILED should not exist before 5th failure
                $this->assertSame(0, DB::table('api_events')->where('code', 'DELIVERY_FAILED')->count());

                // Travel to next_attempt_at for next retry
                $currentTime = $nextAttemptAt;
                CarbonImmutable::setTestNow($currentTime);
            } else {
                // After the 5th attempt: status is FAILED, last_error set (redacted, <= 500 chars)
                $this->assertSame('FAILED', $row->status);
                $this->assertNotNull($row->last_error);
                $this->assertLessThanOrEqual(500, mb_strlen((string) $row->last_error));
                $this->assertStringNotContainsString('0901234567', (string) $row->last_error);
                $this->assertStringNotContainsString('test.user@example.com', (string) $row->last_error);
                $this->assertStringContainsString('[phone]', (string) $row->last_error);
                $this->assertStringContainsString('[email]', (string) $row->last_error);

                // 1 api_events row DELIVERY_FAILED
                $apiEvents = DB::table('api_events')
                    ->where('code', 'DELIVERY_FAILED')
                    ->get();
                $this->assertCount(1, $apiEvents);

                $event = $apiEvents->first();
                $this->assertNotNull($event);
                $this->assertSame((string) $smsRow->event_id, $event->correlation_id);
                $this->assertSame('outbox-relay', $event->route);
                $this->assertSame('-', $event->method);
                $this->assertNull($event->status);
                $this->assertSame('error', $event->outcome);
                $this->assertSame($world->dealership->id, $event->dealership_id);
                $this->assertSame($apptId, $event->appointment_id);

                /** @var array<string, mixed> $detail */
                $detail = json_decode((string) $event->detail, true);
                $this->assertSame('SMS', $detail['channel']);
                $this->assertStringContainsString('Confirmation SMS', $detail['event']);
                $this->assertStringNotContainsString('0901234567', (string) $event->detail);
                $this->assertStringNotContainsString('test.user@example.com', (string) $event->detail);
            }
        }
    }

    /**
     * TC-11-18 (INV-11.4; Integration):
     * given One SMS row; the channel throws an Error that is not DeliveryFailed
     *       and the test aborts the run each time (simulated crash, no result recorded);
     * when runOnce + travel(61)->seconds(), 6 times;
     * then attempts stops at 5; after the 5th lease expires the row is FAILED,
     *      last_error 'NO_RESULT', one DELIVERY_FAILED event; the claim never sees it again.
     */
    public function test_INV_11_4_abandoned_attempts_end_in_failed(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $t0 = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($t0);

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => '2026-11-02T09:00:00+07:00',
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Keep only one SMS row for this test
        DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('channel', '!=', 'SMS')
            ->delete();

        $smsRow = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('channel', 'SMS')
            ->first();
        $this->assertNotNull($smsRow);
        $this->assertSame(0, $smsRow->attempts);
        $this->assertSame('PENDING', $smsRow->status);

        // Simulated crash channel throwing Error (not DeliveryFailed)
        $crashingChannel = new class implements NotificationChannel
        {
            public function send(OutboxMessage $message, Recipient $recipient): void
            {
                throw new Error('Simulated worker process crash during channel dispatch');
            }
        };

        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $crashingChannel);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);

        $currentTime = $t0;

        // Run runOnce + travel(61)->seconds(), 6 times
        for ($i = 1; $i <= 6; $i++) {
            CarbonImmutable::setTestNow($currentTime);

            try {
                $relay->runOnce($currentTime);
            } catch (Error $e) {
                $this->assertSame('Simulated worker process crash during channel dispatch', $e->getMessage());
            }

            $currentTime = $currentTime->addSeconds(61);
        }

        // Row assertions: attempts stops at 5; status is FAILED, last_error 'NO_RESULT'
        $updatedRow = DB::table('outbox_messages')->where('id', $smsRow->id)->first();
        $this->assertNotNull($updatedRow);
        $this->assertSame('FAILED', $updatedRow->status);
        $this->assertSame(5, $updatedRow->attempts, 'Attempts must not exceed 5');
        $this->assertSame('NO_RESULT', $updatedRow->last_error);

        // Exactly one DELIVERY_FAILED api_events row
        $apiEvents = DB::table('api_events')
            ->where('code', 'DELIVERY_FAILED')
            ->where('correlation_id', (string) $smsRow->event_id)
            ->get();
        $this->assertCount(1, $apiEvents);

        $event = $apiEvents->first();
        $this->assertNotNull($event);
        $this->assertSame('outbox-relay', $event->route);
        $this->assertSame('-', $event->method);
        $this->assertNull($event->status);
        $this->assertSame('error', $event->outcome);
        $this->assertSame($world->dealership->id, $event->dealership_id);
        $this->assertSame($apptId, $event->appointment_id);

        /** @var array<string, mixed> $detail */
        $detail = json_decode((string) $event->detail, true);
        $this->assertSame('SMS', $detail['channel']);
        $this->assertSame('NO_RESULT', $detail['error']);

        // The claim never sees it again: running relay again claims 0
        CarbonImmutable::setTestNow($currentTime);
        $subsequentResult = $relay->runOnce($currentTime);
        $this->assertSame(0, $subsequentResult->claimed);
        $this->assertSame(0, $subsequentResult->failed);
    }

    /**
     * TC-11-09 (AC-11.7, D-11.7; Integration):
     * given Confirmation rows PENDING; the booking then cancelled (cancel rows written);
     * when runOnce;
     * then Confirmation rows SKIPPED; cancellation rows SENT.
     * Test: …::test_AC_11_7_stale_messages_are_skipped.
     */
    public function test_AC_11_7_stale_messages_are_skipped(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $bookingNow = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($bookingNow);

        $desiredStart = '2026-11-02T09:00:00+07:00';
        $key = (string) Str::uuid();

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $desiredStart,
        ], [
            'Idempotency-Key' => $key,
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Precondition: 3 confirmation rows are PENDING
        $confirmationRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentConfirmed->value)
            ->get();
        $this->assertCount(3, $confirmationRows);
        foreach ($confirmationRows as $row) {
            $this->assertSame('PENDING', $row->status);
            $this->assertSame(0, $row->attempts);
        }

        // Cancel the booking before the relay runs (cancel rows written)
        $cancelNow = CarbonImmutable::parse('2026-11-02 08:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($cancelNow);

        $cancelResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$apptId}/cancel", [
            'reason' => 'CUSTOMER_BUSY',
            'note' => 'Customer called to cancel',
        ], [
            'If-Match' => '"1"',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $cancelResponse->assertStatus(200);

        // Verify cancellation rows exist and are PENDING
        $cancellationRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentCancelled->value)
            ->get();
        $this->assertCount(3, $cancellationRows);
        foreach ($cancellationRows as $row) {
            $this->assertSame('PENDING', $row->status);
            $this->assertSame(0, $row->attempts);
        }

        // Set up recording fake channels for SMS & DMS, and Mail fake for EMAIL
        $smsFake = new RecordingChannel;
        $dmsFake = new RecordingChannel;

        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $smsFake);
        $registry->register(OutboxChannel::DMS, $dmsFake);
        Mail::fake();

        // Run relay runOnce
        $relayNow = CarbonImmutable::parse('2026-11-02 08:05:10', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($relayNow);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);
        $result = $relay->runOnce($relayNow);

        $this->assertSame(6, $result->claimed);
        $this->assertSame(3, $result->sent);
        $this->assertSame(0, $result->failed);
        $this->assertSame(3, $result->skipped);

        // Confirmation rows are SKIPPED with reason NO_LONGER_CONFIRMED
        $updatedConfirmationRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentConfirmed->value)
            ->get();
        $this->assertCount(3, $updatedConfirmationRows);
        foreach ($updatedConfirmationRows as $row) {
            $this->assertSame('SKIPPED', $row->status);
            $this->assertSame('NO_LONGER_CONFIRMED', $row->last_error);
            $this->assertNull($row->sent_at);
        }

        // Cancellation rows are SENT
        $updatedCancellationRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentCancelled->value)
            ->get();
        $this->assertCount(3, $updatedCancellationRows);
        foreach ($updatedCancellationRows as $row) {
            $this->assertSame('SENT', $row->status);
            $this->assertNull($row->last_error);
            $this->assertNotNull($row->sent_at);
        }

        // Channels received only the cancellation message
        $this->assertSame(1, $smsFake->count());
        $this->assertSame(OutboxEventType::AppointmentCancelled, $smsFake->lastMessage()?->event_type);

        $this->assertSame(1, $dmsFake->count());
        $this->assertSame(OutboxEventType::AppointmentCancelled, $dmsFake->lastMessage()?->event_type);

        Mail::assertSent(AppointmentNotification::class, function (AppointmentNotification $mail) {
            return $mail->outboxMessage->event_type === OutboxEventType::AppointmentCancelled;
        });
        Mail::assertNotSent(AppointmentNotification::class, function (AppointmentNotification $mail) {
            return $mail->outboxMessage->event_type === OutboxEventType::AppointmentConfirmed;
        });
    }

    /**
     * D-11.7: Booking rescheduled to a different time before confirmation is sent.
     * The confirmation row is SKIPPED with reason START_TIME_CHANGED;
     * The reschedule row is SENT.
     */
    public function test_AC_11_7_rescheduled_booking_skips_outdated_confirmation(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);
        $service = $world->serviceType('OIL_CHANGE');

        $bookingNow = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($bookingNow);

        $desiredStart = '2026-11-02T09:00:00+07:00';
        $key = (string) Str::uuid();

        $bookingResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $service->id,
            'desiredStart' => $desiredStart,
        ], [
            'Idempotency-Key' => $key,
        ]);

        $bookingResponse->assertStatus(201);
        $apptId = $bookingResponse->json('id');
        $this->assertNotNull($apptId);

        // Advisor reschedules appointment to a different time (e.g. 10:00)
        $rescheduleNow = CarbonImmutable::parse('2026-11-02 08:05:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($rescheduleNow);

        $newStart = '2026-11-02T10:00:00+07:00';
        $rescheduleResponse = $this->actingAs($advisor)->postJson("/api/v1/appointments/{$apptId}/reschedule", [
            'newStart' => $newStart,
            'keepTechnician' => true,
        ], [
            'If-Match' => '"1"',
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $rescheduleResponse->assertStatus(200);

        // Set up recording fake channels for SMS & DMS, and Mail fake for EMAIL
        $smsFake = new RecordingChannel;
        $dmsFake = new RecordingChannel;

        /** @var ChannelRegistryInterface $registry */
        $registry = app(ChannelRegistryInterface::class);
        $registry->register(OutboxChannel::SMS, $smsFake);
        $registry->register(OutboxChannel::DMS, $dmsFake);
        Mail::fake();

        // Run relay runOnce
        $relayNow = CarbonImmutable::parse('2026-11-02 08:05:10', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($relayNow);

        /** @var OutboxRelayInterface $relay */
        $relay = app(OutboxRelayInterface::class);
        $result = $relay->runOnce($relayNow);

        $this->assertSame(6, $result->claimed);
        $this->assertSame(3, $result->sent);
        $this->assertSame(0, $result->failed);
        $this->assertSame(3, $result->skipped);

        // Confirmation rows are SKIPPED with reason START_TIME_CHANGED
        $updatedConfirmationRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentConfirmed->value)
            ->get();
        $this->assertCount(3, $updatedConfirmationRows);
        foreach ($updatedConfirmationRows as $row) {
            $this->assertSame('SKIPPED', $row->status);
            $this->assertSame('START_TIME_CHANGED', $row->last_error);
            $this->assertNull($row->sent_at);
        }

        // Reschedule rows are SENT
        $updatedRescheduleRows = DB::table('outbox_messages')
            ->where('appointment_id', $apptId)
            ->where('event_type', OutboxEventType::AppointmentRescheduled->value)
            ->get();
        $this->assertCount(3, $updatedRescheduleRows);
        foreach ($updatedRescheduleRows as $row) {
            $this->assertSame('SENT', $row->status);
            $this->assertNull($row->last_error);
            $this->assertNotNull($row->sent_at);
        }

        // Channels received only the reschedule message
        $this->assertSame(1, $smsFake->count());
        $this->assertSame(OutboxEventType::AppointmentRescheduled, $smsFake->lastMessage()?->event_type);

        $this->assertSame(1, $dmsFake->count());
        $this->assertSame(OutboxEventType::AppointmentRescheduled, $dmsFake->lastMessage()?->event_type);

        Mail::assertSent(AppointmentNotification::class, function (AppointmentNotification $mail) {
            return $mail->outboxMessage->event_type === OutboxEventType::AppointmentRescheduled;
        });
        Mail::assertNotSent(AppointmentNotification::class, function (AppointmentNotification $mail) {
            return $mail->outboxMessage->event_type === OutboxEventType::AppointmentConfirmed;
        });
    }
}
