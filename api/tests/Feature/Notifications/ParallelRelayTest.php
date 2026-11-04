<?php

namespace Tests\Feature\Notifications;

use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

/**
 * @group slow
 */
class ParallelRelayTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('notification_records');
        Schema::create('notification_records', function (Blueprint $table) {
            $table->id();
            $table->uuid('event_id');
            $table->string('channel');
            $table->timestamp('created_at')->nullable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('notification_records');

        parent::tearDown();
    }

    /**
     * TC-11-08 (INV-11.3; Concurrency):
     * given 100 PENDING rows; a channel that records eventId:channel to a table;
     * when 2 processes run outbox:relay at the same moment;
     * then Every row sent exactly once (no duplicate records).
     * Test: ParallelRelayTest::test_two_relays_never_send_the_same_row.
     */
    public function test_two_relays_never_send_the_same_row(): void
    {
        $world = World::saigonD7();
        ['customer' => $customer] = $world->customerWithVehicle();
        $customer->update(['phone' => '0901234567']);

        $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'UTC');
        CarbonImmutable::setTestNow($now);

        $nowIso = $now->toIso8601String();
        $totalRows = 100;
        $rows = [];
        $eventIds = [];

        for ($i = 0; $i < $totalRows; $i++) {
            $eventId = (string) Str::uuid();
            $eventIds[] = $eventId;
            $rows[] = [
                'id' => (string) Str::uuid(),
                'event_id' => $eventId,
                'dealership_id' => $world->dealership->id,
                'appointment_id' => null,
                'event_type' => 'AppointmentCancelled',
                'channel' => 'SMS',
                'payload' => json_encode([
                    'customerId' => (string) $customer->id,
                    'reference' => "TEST-{$i}",
                ], JSON_THROW_ON_ERROR),
                'status' => 'PENDING',
                'attempts' => 0,
                'next_attempt_at' => $nowIso,
                'last_error' => null,
                'sent_at' => null,
                'dedupe_key' => "cancel-parallel-{$i}",
                'created_at' => $nowIso,
                'updated_at' => $nowIso,
            ];
        }

        DB::table('outbox_messages')->insert($rows);

        // Precondition: exactly 100 PENDING rows
        $this->assertSame(100, DB::table('outbox_messages')->where('status', 'PENDING')->count());

        $artisan = base_path('artisan');

        $results = Parallel::run(2, function (int $proc, float $targetStartTime) use ($artisan, $nowIso) {
            return "APP_ENV=testing DB_DATABASE=scheduler_testing OUTBOX_SMS_DRIVER=table php {$artisan} outbox:relay --batch=100 --now={$nowIso} --start-at={$targetStartTime}";
        }, delaySeconds: 0.5);

        $this->assertCount(2, $results);
        $this->assertSame(0, $results[0]['status'], "Process 0 failed with output: {$results[0]['raw']}");
        $this->assertSame(0, $results[1]['status'], "Process 1 failed with output: {$results[1]['raw']}");

        // Assert notification_records table has exactly 100 records and zero duplicates
        $records = DB::table('notification_records')->get();
        $this->assertCount(100, $records, 'Expected exactly 100 recorded notifications across both relays');

        $recordedEventIds = $records->pluck('event_id')->all();
        $this->assertCount(100, array_unique($recordedEventIds), 'Zero duplicate sends: every event must be recorded once');

        sort($eventIds);
        sort($recordedEventIds);
        $this->assertSame($eventIds, $recordedEventIds);

        // Assert all 100 outbox rows are marked SENT with attempts = 1 and sent_at set
        $sentRows = DB::table('outbox_messages')->whereIn('event_id', $eventIds)->get();
        $this->assertCount(100, $sentRows);
        foreach ($sentRows as $row) {
            $this->assertSame('SENT', $row->status);
            $this->assertSame(1, (int) $row->attempts);
            $this->assertNotNull($row->sent_at);
            $this->assertNull($row->last_error);
        }

        // Assert that the total messages claimed across both workers equals 100
        $claimedProc0 = 0;
        $claimedProc1 = 0;
        if (preg_match('/claimed=(\d+)/', $results[0]['raw'], $matches0)) {
            $claimedProc0 = (int) $matches0[1];
        }
        if (preg_match('/claimed=(\d+)/', $results[1]['raw'], $matches1)) {
            $claimedProc1 = (int) $matches1[1];
        }

        $this->assertSame(100, $claimedProc0 + $claimedProc1, "Total claimed across both workers ({$claimedProc0} + {$claimedProc1}) must equal 100");
    }
}
