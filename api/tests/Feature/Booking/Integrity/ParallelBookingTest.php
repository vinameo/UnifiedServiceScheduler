<?php

namespace Tests\Feature\Booking\Integrity;

use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Tests\Support\Concurrency\Parallel;
use Tests\Support\World;
use Tests\TestCase;

class ParallelBookingTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_04_1_only_one_of_twenty_parallel_bookings_wins(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['Trần Minh An']);
        $world->onlyBays(['K1']);
        $service = $world->serviceType('OIL_CHANGE');

        // Choose a target date that is a future open weekday (e.g. Wednesday 09:00 local, 02:00 UTC)
        $targetLocal = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY)->setTime(9, 0, 0);
        $startStr = $targetLocal->toIso8601String();

        // Create 20 customers and 20 vehicles
        $users = [];
        $customers = [];
        $vehicles = [];

        for ($i = 0; $i < 20; $i++) {
            $user = User::factory()->customer()->create([
                'email' => "parallel_user_{$i}@customer.test",
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

        $results = Parallel::run(20, function (int $i) use ($users, $customers, $vehicles, $dealershipId, $serviceTypeId, $startStr) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/_testing/appointments',
                'user' => $users[$i],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'customerId' => $customers[$i]->id,
                    'vehicleId' => $vehicles[$i]->id,
                    'serviceTypeId' => $serviceTypeId,
                    'start' => $startStr,
                ],
            ];
        }, delaySeconds: 1.5);

        $this->assertCount(20, $results);

        $statuses = [];
        foreach ($results as $res) {
            $code = $res['status'];
            $statuses[$code] = ($statuses[$code] ?? 0) + 1;
        }

        // Exactly 1 x 201, 19 x 409 NO_AVAILABILITY
        $this->assertSame(1, $statuses[201] ?? 0, 'Expected exactly 1 booking to succeed with 201');
        $this->assertSame(19, $statuses[409] ?? 0, 'Expected exactly 19 bookings to fail with 409');

        // Exactly 1 appointment row
        $this->assertSame(1, DB::table('appointments')->count());

        // Exactly 3 resource allocations (1 technician, 1 bay, 1 vehicle)
        $this->assertSame(3, DB::table('resource_allocations')->where('active', true)->count());
    }

    public function test_AC_04_2_two_pairs_give_exactly_two_bookings(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['Trần Minh An', 'Lý Hoàng Dũng']);
        $world->onlyBays(['K1', 'K2']);
        $service = $world->serviceType('OIL_CHANGE');

        $targetLocal = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::THURSDAY)->setTime(9, 0, 0);
        $startStr = $targetLocal->toIso8601String();

        $users = [];
        $customers = [];
        $vehicles = [];

        for ($i = 0; $i < 10; $i++) {
            $user = User::factory()->customer()->create([
                'email' => "parallel_two_user_{$i}@customer.test",
            ]);
            $customer = Customer::factory()->create([
                'user_id' => $user->id,
                'full_name' => "Customer Two {$i}",
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

        $results = Parallel::run(10, function (int $i) use ($users, $customers, $vehicles, $dealershipId, $serviceTypeId, $startStr) {
            return [
                'method' => 'POST',
                'uri' => '/api/v1/_testing/appointments',
                'user' => $users[$i],
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'body' => [
                    'dealershipId' => $dealershipId,
                    'customerId' => $customers[$i]->id,
                    'vehicleId' => $vehicles[$i]->id,
                    'serviceTypeId' => $serviceTypeId,
                    'start' => $startStr,
                ],
            ];
        }, delaySeconds: 1.5);

        $this->assertCount(10, $results);

        $statuses = [];
        foreach ($results as $res) {
            $code = $res['status'];
            $statuses[$code] = ($statuses[$code] ?? 0) + 1;
        }

        // Exactly 2 x 201, 8 x 409
        $this->assertSame(2, $statuses[201] ?? 0, 'Expected exactly 2 bookings to succeed with 201');
        $this->assertSame(8, $statuses[409] ?? 0, 'Expected exactly 8 bookings to fail with 409');

        // Exactly 2 appointment rows with different technicians and different bays
        $appointments = DB::table('appointments')->get();
        $this->assertCount(2, $appointments);
        $first = $appointments->first();
        $last = $appointments->last();
        $this->assertNotNull($first);
        $this->assertNotNull($last);
        $this->assertNotSame($first->technician_id, $last->technician_id);
        $this->assertNotSame($first->bay_id, $last->bay_id);
    }
}
