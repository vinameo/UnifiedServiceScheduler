<?php

namespace Tests\Feature\Booking\Integrity;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\AssertsNoOverlap;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelCreateBookingTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_04_1_through_the_real_endpoint(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['Trần Minh An']);
        $world->onlyBays(['K1']);
        $service = $world->serviceType('OIL_CHANGE');

        $targetLocal = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY)->setTime(9, 0, 0);
        $startStr = $targetLocal->toIso8601String();

        $users = [];
        $customers = [];
        $vehicles = [];

        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->customer()->create([
                'email' => "parallel_real_user_{$i}@customer.test",
            ]);
            $customer = Customer::factory()->create([
                'user_id' => $user->id,
                'full_name' => "Customer {$i}",
            ]);
            $vehicle = Vehicle::factory()->create([
                'customer_id' => $customer->id,
            ]);

            $users[] = $user;
            $customers[] = $customer;
            $vehicles[] = $vehicle;
        }

        $dealershipId = $world->dealership->id;
        $serviceTypeId = $service->id;

        $results = Parallel::run(20, function (int $i) use ($users, $vehicles, $dealershipId, $serviceTypeId, $startStr) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/appointments',
                'user' => $users[$i],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                    'Idempotency-Key' => (string) Str::uuid(),
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'vehicleId' => $vehicles[$i]->id,
                    'serviceTypeId' => $serviceTypeId,
                    'desiredStart' => $startStr,
                    'reminderOptIn' => true,
                    'mode' => 'CONFIRM',
                ],
            ];
        }, delaySeconds: 1.5);

        $this->assertCount(20, $results);

        $statuses = [];
        foreach ($results as $res) {
            $code = $res['status'];
            $statuses[$code] = ($statuses[$code] ?? 0) + 1;
        }

        $this->assertSame(1, $statuses[201] ?? 0, 'Expected exactly 1 booking to succeed with 201');
        $this->assertSame(19, $statuses[409] ?? 0, 'Expected exactly 19 bookings to fail with 409');

        $this->assertSame(1, DB::table('appointments')->count());
        $this->assertSame(3, DB::table('resource_allocations')->where('active', true)->count());

        $this->assertNoOverlappingAllocations();
    }
}

