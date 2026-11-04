<?php

namespace Tests\Feature\Booking\Hold;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AppointmentFactory;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

/**
 * @group slow
 */
class ParallelHoldExpiryTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-10-03 (AC-10.3, INV-10.3; Concurrency (@group slow)):
     * Given 100 holds (half with a vehicle, half without), each with expires_at = T;
     * When for each, process 1 runs holds:expire --now=T+1s, process 2 confirms at T − 300 ms, released together;
     * Then each booking ends CONFIRMED (3 active allocations) or EXPIRED (0 active); never mixed;
     * the losing confirm got 412 (status EXPIRED) or 410 (status EXPIRED);
     * across the 100 runs both outcomes occur at least once (otherwise the test did not race).
     */
    public function test_AC_10_3_job_and_confirm_never_both_win(): void
    {
        $world = World::saigonD7();
        $service = $world->serviceType('OIL_CHANGE');

        $baseTime = CarbonImmutable::parse('2026-11-04 10:00:00', 'Asia/Ho_Chi_Minh');

        $confirmedCount = 0;
        $expiredCount = 0;
        $totalRuns = 100;

        for ($run = 0; $run < $totalRuns; $run++) {
            ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();

            // Half with a vehicle, half without
            $hasVehicle = ($run % 2 === 0);
            $holdVehicle = $hasVehicle ? $vehicle : null;

            $slotStart = $baseTime->addDays(7)->addHours($run);
            $expiresAt = $baseTime->addMinutes(5);

            $hold = AppointmentFactory::hold(
                customer: $customer,
                start: $slotStart,
                expiresAt: $expiresAt,
                vehicle: $holdVehicle,
                dealership: $world->dealership,
                serviceType: $service,
            );

            $this->assertSame(1, (int) $hold->version);
            $this->assertSame(AppointmentStatus::HELD, $hold->status);

            // Job evaluates at T + 1s, confirm evaluates at T - 300ms
            $nowJob = $expiresAt->addSecond()->toIso8601String();
            $nowConfirm = $expiresAt->subMilliseconds(300)->toIso8601String();
            $holdId = $hold->id;
            $vehicleId = $vehicle->id;

            // Offset sweep between 0.05s and 0.185s to thoroughly explore the race window
            $jobOffsetSeconds = 0.05 + (($run % 10) * 0.015);

            $results = Parallel::run(2, function (int $proc, float $targetStartTime) use (
                $nowJob,
                $nowConfirm,
                $holdId,
                $vehicleId,
                $customerUser,
                $jobOffsetSeconds
            ) {
                if ($proc === 0) {
                    $artisan = base_path('artisan');
                    $jobStart = $targetStartTime + $jobOffsetSeconds;

                    return "APP_ENV=testing DB_DATABASE=scheduler_testing php {$artisan} holds:expire --now={$nowJob} --start-at={$jobStart}";
                }

                return [
                    'method' => 'POST',
                    'uri' => "/api/v1/appointments/{$holdId}/confirm",
                    'user' => $customerUser,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'If-Match' => '"1"',
                        'Idempotency-Key' => (string) Str::uuid(),
                    ],
                    'body' => [
                        'vehicleId' => $vehicleId,
                        'reminderOptIn' => true,
                    ],
                    'now' => $nowConfirm,
                ];
            }, delaySeconds: 0.35);

            $jobResult = $results[0];
            $confirmResult = $results[1];

            // Job process always exits cleanly (status 0)
            $this->assertSame(0, $jobResult['status'], "Job failed on run {$run} with output: {$jobResult['raw']}");

            // Verify booking state in DB
            $fresh = DB::table('appointments')->where('id', $holdId)->first();
            $this->assertNotNull($fresh, "Appointment {$holdId} not found on run {$run}");
            $this->assertSame(2, (int) $fresh->version, "Run {$run} expected version 2");

            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $holdId)
                ->where('active', true)
                ->count();

            if ($fresh->status === AppointmentStatus::CONFIRMED->value) {
                $confirmedCount++;

                // Confirm won: HTTP 200, status CONFIRMED, exactly 3 active allocations (TECH, BAY, VEHICLE)
                $this->assertSame(200, $confirmResult['status'], "Run {$run}: expected 200 for confirmed hold");
                $this->assertSame(AppointmentStatus::CONFIRMED->value, $confirmResult['body']['status'] ?? null);
                $this->assertSame(3, $activeAllocations, "Run {$run}: CONFIRMED appointment must have exactly 3 active allocations");
                $this->assertNull($fresh->expires_at, "Run {$run}: CONFIRMED appointment must have expires_at null");
                $this->assertSame($vehicleId, $fresh->vehicle_id, "Run {$run}: CONFIRMED appointment must have vehicle assigned");
            } elseif ($fresh->status === AppointmentStatus::EXPIRED->value) {
                $expiredCount++;

                // Job won: confirm gets 412 (status EXPIRED) or 410, exactly 0 active allocations
                $this->assertContains($confirmResult['status'], [410, 412], "Run {$run}: losing confirm must receive 410 or 412");
                if ($confirmResult['status'] === 412) {
                    $this->assertSame('APPOINTMENT_MODIFIED', $confirmResult['body']['code'] ?? null);
                    $this->assertSame(
                        AppointmentStatus::EXPIRED->value,
                        $confirmResult['body']['appointment']['status'] ?? null,
                        "Run {$run}: 412 body appointment must have status EXPIRED"
                    );
                } else {
                    $this->assertSame('HOLD_EXPIRED', $confirmResult['body']['code'] ?? null);
                }
                $this->assertSame(0, $activeAllocations, "Run {$run}: EXPIRED appointment must have 0 active allocations");
            } else {
                $this->fail("Run {$run}: Unexpected appointment status {$fresh->status}");
            }
        }

        // Across the 100 runs both outcomes occur at least once
        $this->assertGreaterThan(0, $confirmedCount, 'Across 100 runs, CONFIRMED must occur at least once');
        $this->assertGreaterThan(0, $expiredCount, 'Across 100 runs, EXPIRED must occur at least once');
        $this->assertSame($totalRuns, $confirmedCount + $expiredCount);

        // Verify database allocation integrity
        $this->assertNoOverlappingAllocations();
    }
}
