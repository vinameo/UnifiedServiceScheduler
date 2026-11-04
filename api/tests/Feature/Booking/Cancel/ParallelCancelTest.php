<?php

namespace Tests\Feature\Booking\Cancel;

use App\Domain\Booking\AppointmentStatus;
use App\Domain\Notifications\OutboxEventType;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelCancelTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_07_9_parallel_cancels_all_succeed_once(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        // Start time 7 days in the future so customer is well outside 2-hour window
        $future = CarbonImmutable::now('UTC')->addDays(7)->setTime(10, 0, 0);
        $apptId = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $apptId,
            'reference' => 'APT-'.CarbonImmutable::now()->format('Ymd').'-0079',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $future,
            'end_at' => $future->addHour(),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => CarbonImmutable::now(),
            'updated_at' => CarbonImmutable::now(),
        ]);

        $period = "[{$future->toIso8601String()}, {$future->addHour()->toIso8601String()})";
        DB::table('resource_allocations')->insert([
            [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apptId,
                'dealership_id' => $world->dealership->id,
                'resource_type' => 'TECHNICIAN',
                'resource_id' => $tech->id,
                'period' => $period,
                'active' => true,
                'created_at' => CarbonImmutable::now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apptId,
                'dealership_id' => $world->dealership->id,
                'resource_type' => 'BAY',
                'resource_id' => $bay->id,
                'period' => $period,
                'active' => true,
                'created_at' => CarbonImmutable::now(),
            ],
            [
                'id' => (string) Str::uuid(),
                'appointment_id' => $apptId,
                'dealership_id' => $world->dealership->id,
                'resource_type' => 'VEHICLE',
                'resource_id' => $vehicle->id,
                'period' => $period,
                'active' => true,
                'created_at' => CarbonImmutable::now(),
            ],
        ]);

        // 5 processes cancel with same If-Match "1" and different Idempotency keys
        $results = Parallel::run(5, function (int $i) use ($user, $apptId) {
            return [
                'method' => 'POST',
                'uri' => "/api/v1/appointments/{$apptId}/cancel",
                'user' => $user,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'reason' => 'SOMETHING_CAME_UP',
                    'note' => "Parallel cancel request {$i}",
                ],
            ];
        }, delaySeconds: 0.35);

        $this->assertCount(5, $results);

        // AC-07.9: All 5 get 200 CANCELLED
        foreach ($results as $i => $res) {
            $this->assertSame(
                200,
                $res['status'],
                "Expected 200 for process {$i}, got {$res['status']}. Body: ".json_encode($res['body'])
            );
            $this->assertSame('CANCELLED', $res['body']['status'] ?? null);
        }

        // Fresh appointment in DB: version = 2, status = CANCELLED
        $fresh = Appointment::findOrFail($apptId);
        $this->assertSame(AppointmentStatus::CANCELLED, $fresh->status);
        $this->assertSame(2, $fresh->version);

        // Exactly 1 CANCELLED history row in appointment_events
        $historyCount = DB::table('appointment_events')
            ->where('appointment_id', $apptId)
            ->where('to_status', 'CANCELLED')
            ->count();
        $this->assertSame(1, $historyCount);

        // Exactly 1 outbox event for AppointmentCancelled
        // Exactly 1 outbox event (1 distinct event_id across the outbox channel rows)
        $outboxEventIds = DB::table('outbox_messages')
            ->where('event_type', OutboxEventType::AppointmentCancelled->value)
            ->where('payload->appointmentId', $apptId)
            ->pluck('event_id')
            ->unique();
        $this->assertCount(1, $outboxEventIds);

        // No active allocations remain
        $activeAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $apptId)
            ->where('active', true)
            ->count();
        $this->assertSame(0, $activeAllocations);

        $this->assertNoOverlappingAllocations();
    }
}
