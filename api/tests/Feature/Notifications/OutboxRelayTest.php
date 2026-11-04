<?php

namespace Tests\Feature\Notifications;

use App\Domain\Notifications\Channels\ChannelRegistryInterface;
use App\Domain\Notifications\Channels\FailingChannel;
use App\Domain\Notifications\Mail\AppointmentNotification;
use App\Domain\Notifications\OutboxChannel;
use App\Domain\Notifications\Services\OutboxRelayInterface;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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
}
