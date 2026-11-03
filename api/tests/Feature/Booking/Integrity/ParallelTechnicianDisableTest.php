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

class ParallelTechnicianDisableTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    public function test_AC_01_9_disable_and_booking_never_both_win(): void
    {
        $world = World::saigonD7();
        $world->onlyTechnicians(['Võ Thị Chi']);
        $world->onlyBays(['K1']);
        $service = $world->serviceType('OIL_CHANGE');
        $dealership = $world->dealership;
        $admin = $world->admin();
        $chi = $world->technician('Võ Thị Chi');

        $targetLocal = CarbonImmutable::now('Asia/Ho_Chi_Minh')->addWeeks(2)->next(CarbonImmutable::WEDNESDAY)->setTime(9, 0, 0);
        $startStr = $targetLocal->toIso8601String();

        $runs = 10;

        for ($run = 0; $run < $runs; $run++) {
            // Clean state for each run
            DB::table('resource_allocations')->delete();
            DB::table('appointments')->delete();
            DB::table('technicians')->where('id', $chi->id)->update(['active' => true]);

            $customerUser = User::factory()->customer()->create([
                'email' => "parallel_disable_cust_{$run}@customer.test",
            ]);
            $customer = Customer::factory()->create([
                'user_id' => $customerUser->id,
                'full_name' => "Customer Disable {$run}",
            ]);
            $vehicle = Vehicle::factory()->create([
                'customer_id' => $customer->id,
            ]);

            $dealershipId = $dealership->id;
            $chiId = $chi->id;
            $serviceTypeId = $service->id;
            $customerId = $customer->id;
            $vehicleId = $vehicle->id;

            $results = Parallel::run(2, function (int $proc) use (
                $customerUser,
                $admin,
                $dealershipId,
                $chiId,
                $customerId,
                $vehicleId,
                $serviceTypeId,
                $startStr
            ) {
                if ($proc === 0) {
                    // Process 0: Booking request
                    return [
                        'method' => 'POST',
                        'uri' => '/api/v1/_testing/appointments',
                        'user' => $customerUser,
                        'headers' => [
                            'Accept' => 'application/json',
                            'Content-Type' => 'application/json',
                            'Idempotency-Key' => (string) Str::uuid(),
                        ],
                        'body' => [
                            'dealershipId' => $dealershipId,
                            'customerId' => $customerId,
                            'vehicleId' => $vehicleId,
                            'serviceTypeId' => $serviceTypeId,
                            'start' => $startStr,
                        ],
                    ];
                }

                // Process 1: Disable technician request
                return [
                    'method' => 'PUT',
                    'uri' => "/api/v1/dealerships/{$dealershipId}/technicians/{$chiId}/status",
                    'user' => $admin,
                    'headers' => [
                        'Accept' => 'application/json',
                        'Content-Type' => 'application/json',
                    ],
                    'body' => [
                        'active' => false,
                    ],
                ];
            }, delaySeconds: 0.35);

            $this->assertCount(2, $results);

            $bookingStatus = $results[0]['status'];
            $disableStatus = $results[1]['status'];

            $chiFresh = DB::table('technicians')->where('id', $chiId)->first();
            $this->assertNotNull($chiFresh);

            $activeChiAllocations = DB::table('resource_allocations')
                ->where('resource_type', 'TECHNICIAN')
                ->where('resource_id', $chiId)
                ->where('active', true)
                ->count();

            // Invariant INV-01.7: never Chi inactive with an active allocation
            $this->assertFalse(
                ! $chiFresh->active && $activeChiAllocations > 0,
                "Run {$run}: Inactive technician cannot have active allocations!"
            );

            // Valid outcomes:
            // Case A: Booking won (201), Disable refused (409), Chi remains active
            // Case B: Disable won (200), Booking refused (409 NO_AVAILABILITY), Chi becomes inactive
            if ($bookingStatus === 201) {
                $this->assertSame(409, $disableStatus, "Run {$run}: If booking won (201), disable must be refused with 409");
                $this->assertTrue((bool) $chiFresh->active, "Run {$run}: Chi must remain active");
                $this->assertSame(1, $activeChiAllocations, "Run {$run}: Chi must have 1 active allocation");
            } else {
                $this->assertSame(409, $bookingStatus, "Run {$run}: If booking did not win, it must receive 409");
                $this->assertSame(200, $disableStatus, "Run {$run}: If booking was refused, disable must succeed with 200");
                $this->assertFalse((bool) $chiFresh->active, "Run {$run}: Chi must be inactive");
                $this->assertSame(0, $activeChiAllocations, "Run {$run}: Chi must have 0 active allocations");
            }

            // Database overlap check
            $this->assertNoOverlappingAllocations();
        }
    }
}
