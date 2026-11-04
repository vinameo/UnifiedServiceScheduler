<?php

namespace Tests\Feature\Booking\Integrity;

use App\Domain\Booking\AppointmentStatus;
use App\Models\Appointment;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelRescheduleTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-09-12 (AC-09.10, AC-04.6; Concurrency): Given X v3, when Process 1 cancels
     * and Process 2 reschedules, both with If-Match "3" at the same moment;
     * then one returns 200, one returns 412; final version 4; status either
     * CANCELLED (allocations inactive) or CONFIRMED at new time; overlap 0.
     */
    public function test_AC_04_6_cancel_and_reschedule_on_the_same_version(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $targetDate = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY);
        $initialStart = $targetDate->setTime(9, 0, 0)->toIso8601String();
        $newStart = $targetDate->setTime(14, 0, 0)->toIso8601String();

        // 1. Create appointment X at 09:00 (starts at version 1)
        $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $serviceType->id,
            'desiredStart' => $initialStart,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $createResponse->assertStatus(201);
        $appointmentId = $createResponse->json('id');

        // 2. Bump version to 3 (monotonic version trigger requires step-by-step update)
        DB::table('appointments')->where('id', $appointmentId)->update(['version' => 2]);
        DB::table('appointments')->where('id', $appointmentId)->update(['version' => 3]);

        /** @var Appointment $before */
        $before = Appointment::findOrFail($appointmentId);
        $this->assertSame(3, $before->version);
        $this->assertSame(AppointmentStatus::CONFIRMED, $before->status);

        // 3. Run cancel and reschedule concurrently with If-Match: "3"
        $results = Parallel::run(2, function (int $proc) use ($advisor, $appointmentId, $newStart) {
            if ($proc === 0) {
                return [
                    'method' => 'POST',
                    'uri' => "/api/v1/appointments/{$appointmentId}/cancel",
                    'user' => $advisor,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                        'If-Match' => '"3"',
                        'Idempotency-Key' => (string) Str::uuid(),
                    ],
                    'body' => [
                        'reason' => 'CUSTOMER_REQUEST',
                        'note' => 'Cancelling parallel test',
                    ],
                ];
            }

            return [
                'method' => 'POST',
                'uri' => "/api/v1/appointments/{$appointmentId}/reschedule",
                'user' => $advisor,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'If-Match' => '"3"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'newStart' => $newStart,
                    'keepTechnician' => true,
                ],
            ];
        }, delaySeconds: 0.35);

        $this->assertCount(2, $results);

        $statuses = [$results[0]['status'], $results[1]['status']];
        sort($statuses);
        $this->assertSame([200, 412], $statuses, 'Expected exactly one 200 and one 412');

        // Check final version is exactly 4
        /** @var Appointment $fresh */
        $fresh = Appointment::findOrFail($appointmentId);
        $this->assertSame(4, $fresh->version, 'Final version must be 4');
        $this->assertContains($fresh->status, [AppointmentStatus::CANCELLED, AppointmentStatus::CONFIRMED]);

        if ($fresh->status === AppointmentStatus::CANCELLED) {
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentId)
                ->where('active', true)
                ->count();
            $this->assertSame(0, $activeAllocations, 'Cancelled appointment must have no active allocations');
        } else {
            $this->assertTrue(
                CarbonImmutable::parse($fresh->start_at)->equalTo(CarbonImmutable::parse($newStart)),
                'Rescheduled appointment must be at new start time'
            );
            $activeAllocations = DB::table('resource_allocations')
                ->where('appointment_id', $appointmentId)
                ->where('active', true)
                ->count();
            $this->assertSame(3, $activeAllocations, 'Rescheduled appointment must have 3 active allocations');
        }

        $this->assertNoOverlappingAllocations();
    }

    /**
     * TC-09-14 (INV-09.2; Concurrency): Given X v1, when two processes reschedule
     * to different free times with If-Match "1", then one returns 200, one 412;
     * final version 2; overlap 0.
     */
    public function test_two_reschedules_one_wins(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $serviceType = $world->serviceType('OIL_CHANGE');

        $targetDate = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY);
        $initialStart = $targetDate->setTime(9, 0, 0)->toIso8601String();
        $slotA = $targetDate->setTime(11, 0, 0)->toIso8601String();
        $slotB = $targetDate->setTime(14, 0, 0)->toIso8601String();

        // 1. Create appointment X at 09:00 (version 1)
        $createResponse = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
            'customerId' => $customer->id,
            'vehicleId' => $vehicle->id,
            'serviceTypeId' => $serviceType->id,
            'desiredStart' => $initialStart,
        ], [
            'Idempotency-Key' => (string) Str::uuid(),
        ]);
        $createResponse->assertStatus(201);
        $appointmentId = $createResponse->json('id');

        /** @var Appointment $before */
        $before = Appointment::findOrFail($appointmentId);
        $this->assertSame(1, $before->version);
        $this->assertSame(AppointmentStatus::CONFIRMED, $before->status);

        // 2. Run two reschedules concurrently with If-Match: "1" to slot A and slot B
        $results = Parallel::run(2, function (int $proc) use ($advisor, $appointmentId, $slotA, $slotB) {
            $slot = $proc === 0 ? $slotA : $slotB;

            return [
                'method' => 'POST',
                'uri' => "/api/v1/appointments/{$appointmentId}/reschedule",
                'user' => $advisor,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'If-Match' => '"1"',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'newStart' => $slot,
                    'keepTechnician' => true,
                ],
            ];
        }, delaySeconds: 0.35);

        $this->assertCount(2, $results);

        $statuses = [$results[0]['status'], $results[1]['status']];
        sort($statuses);
        $this->assertSame([200, 412], $statuses, 'Expected exactly one 200 and one 412');

        // Check final version is exactly 2
        /** @var Appointment $fresh */
        $fresh = Appointment::findOrFail($appointmentId);
        $this->assertSame(2, $fresh->version, 'Final version must be 2');
        $this->assertSame(AppointmentStatus::CONFIRMED, $fresh->status);

        $startAt = CarbonImmutable::parse($fresh->start_at);
        $matchesA = $startAt->equalTo(CarbonImmutable::parse($slotA));
        $matchesB = $startAt->equalTo(CarbonImmutable::parse($slotB));
        $this->assertTrue($matchesA || $matchesB, 'Start time must match either winning slot A or slot B');

        $activeAllocations = DB::table('resource_allocations')
            ->where('appointment_id', $appointmentId)
            ->where('active', true)
            ->count();
        $this->assertSame(3, $activeAllocations, 'Winning appointment must have 3 active allocations');

        $this->assertNoOverlappingAllocations();
    }
}
