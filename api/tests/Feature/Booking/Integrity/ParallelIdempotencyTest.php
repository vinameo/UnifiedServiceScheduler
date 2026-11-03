<?php

namespace Tests\Feature\Booking\Integrity;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelIdempotencyTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_04_12_same_key_in_parallel_books_once(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::parse('2026-11-23 09:00:00', 'UTC');
        $key = (string) Str::uuid();

        $dealershipId = (string) $world->dealership->id;
        $customerId = (string) $customer->id;
        $vehicleId = (string) $vehicle->id;
        $serviceTypeId = (string) $service->id;
        $startStr = $now->toIso8601String();

        $results = Parallel::run(10, function (int $i) use ($user, $key, $dealershipId, $customerId, $vehicleId, $serviceTypeId, $startStr) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/_testing/appointments',
                'user' => $user,
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => $key,
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'customerId' => $customerId,
                    'vehicleId' => $vehicleId,
                    'serviceTypeId' => $serviceTypeId,
                    'start' => $startStr,
                ],
            ];
        }, delaySeconds: 1.0);

        $this->assertCount(10, $results);

        $statuses = [];
        $appointmentIds = [];

        foreach ($results as $res) {
            $status = $res['status'];
            $statuses[$status] = ($statuses[$status] ?? 0) + 1;
            $body = $res['body'];

            if ($status === 201) {
                $this->assertArrayHasKey('id', $body);
                $appointmentIds[] = $body['id'];
            } elseif ($status === 409) {
                $this->assertSame('REQUEST_IN_PROGRESS', $body['code'] ?? null);
            }
        }

        // Every response is 201 or 409 REQUEST_IN_PROGRESS
        $validStatuses = ($statuses[201] ?? 0) + ($statuses[409] ?? 0);
        $this->assertSame(10, $validStatuses, 'Expected all responses to be 201 or 409 REQUEST_IN_PROGRESS');

        // At least one 201
        $this->assertGreaterThanOrEqual(1, $statuses[201] ?? 0, 'Expected at least one 201 response');

        // All 201 responses return the same appointment id
        $uniqueIds = array_unique($appointmentIds);
        $this->assertCount(1, $uniqueIds, 'Expected all 201 responses to have the identical appointment id');

        // Exactly 1 booking in database
        $this->assertSame(1, DB::table('appointments')->count());

        // Exactly 3 active resource allocations
        $this->assertSame(3, DB::table('resource_allocations')->where('active', true)->count());
    }
}
