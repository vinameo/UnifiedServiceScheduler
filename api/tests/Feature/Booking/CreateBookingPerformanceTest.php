<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\Repositories\ResourceAllocationRepositoryInterface;
use App\Domain\MasterData\BayType;
use App\Models\Bay;
use App\Models\Customer;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;
use App\Models\Vehicle;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Support\AssertsNoOverlap;
use Tests\TestCase;

#[Group('performance')]
/**
 * @group performance
 */
class CreateBookingPerformanceTest extends TestCase
{
    use AssertsNoOverlap;
    use DatabaseTruncation;

    /**
     * TC-02-30 / NFR-02 / M-02.1:
     * Given Dealership with 20 bays, 30 technicians, 200 bookings that day;
     * when 50 sequential POST /appointments (different times);
     * then p95 < 300 ms.
     */
    public function test_TC_02_30_post_appointments_p95_under_300ms(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 06:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $dealership = Dealership::create([
                'id' => (string) Str::uuid(),
                'code' => 'SGA-PERF',
                'name' => 'Saigon Performance Dealership',
                'branch_name' => 'Benchmark',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'phone' => '028 3812 3456',
            ]);

            // Opening hours on Monday (weekday 1): 07:00 to 20:30
            DealershipOpeningHour::create([
                'dealership_id' => $dealership->id,
                'weekday' => 1,
                'opens_at' => '07:00:00',
                'closes_at' => '20:30:00',
            ]);

            $serviceType = ServiceType::create([
                'id' => (string) Str::uuid(),
                'dealership_id' => $dealership->id,
                'code' => 'OIL_PERF',
                'name' => 'Quick Oil Service',
                'description' => 'Fast oil change for performance test',
                'duration_minutes' => 15,
                'required_level' => 1,
                'required_bay_type' => BayType::QUICK->value,
                'active' => true,
            ]);

            // 20 bays
            $bays = [];
            for ($i = 1; $i <= 20; $i++) {
                $bays[] = Bay::create([
                    'id' => (string) Str::uuid(),
                    'dealership_id' => $dealership->id,
                    'code' => sprintf('BAY-%02d', $i),
                    'name' => sprintf('Bay %02d', $i),
                    'bay_type' => BayType::QUICK->value,
                    'active' => true,
                ]);
            }

            // 30 technicians across levels 1 to 5
            $technicians = [];
            for ($i = 1; $i <= 30; $i++) {
                $technicians[] = Technician::create([
                    'id' => (string) Str::uuid(),
                    'dealership_id' => $dealership->id,
                    'name' => sprintf('Technician %02d', $i),
                    'level' => (($i - 1) % 5) + 1,
                    'active' => true,
                ]);
            }

            $advisor = User::factory()->advisor($dealership)->create();

            // Create 20 customers with 10 vehicles each = 200 vehicles
            $vehicles = [];
            for ($c = 1; $c <= 20; $c++) {
                $user = User::factory()->customer()->create([
                    'email' => "perf_cust_{$c}@example.com",
                ]);
                $customer = Customer::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'full_name' => "Customer {$c}",
                    'phone' => sprintf('090300%04d', $c),
                ]);
                for ($v = 1; $v <= 10; $v++) {
                    $num = ($c - 1) * 10 + $v;
                    $vehicles[] = Vehicle::create([
                        'id' => (string) Str::uuid(),
                        'customer_id' => $customer->id,
                        'licence_plate' => sprintf('51P-%03d.%02d', intdiv($num, 100), $num % 100),
                        'plate_key' => sprintf('51P%05d', $num),
                        'make' => 'Toyota',
                        'model' => 'Corolla',
                        'year' => 2020,
                    ]);
                }
            }

            // Populate 200 existing bookings across 20 distinct times (10 bays used per time)
            $appointmentsData = [];
            $allocationsData = [];
            $nowUtc = $now->setTimezone('UTC')->toIso8601String();

            $allocationRepo = app(ResourceAllocationRepositoryInterface::class);
            $pendingAllocations = [];

            for ($s = 0; $s < 20; $s++) {
                $h = intdiv($s, 2) + 7;
                $m = ($s % 2) * 30;
                $startLocal = CarbonImmutable::parse(sprintf('2026-11-02 %02d:%02d:00', $h, $m), 'Asia/Ho_Chi_Minh');
                $endLocal = $startLocal->addMinutes(15);
                $startUtc = $startLocal->setTimezone('UTC')->format('Y-m-d H:i:sP');
                $endUtc = $endLocal->setTimezone('UTC')->format('Y-m-d H:i:sP');

                for ($b = 0; $b < 10; $b++) {
                    $idx = $s * 10 + $b;
                    $aptId = (string) Str::uuid();
                    $tech = $technicians[$b];
                    $bay = $bays[$b];
                    $veh = $vehicles[$idx];

                    $appointmentsData[] = [
                        'id' => $aptId,
                        'reference' => sprintf('APT-PERF-%04d', $idx + 1),
                        'dealership_id' => $dealership->id,
                        'customer_id' => $veh->customer_id,
                        'vehicle_id' => $veh->id,
                        'service_type_id' => $serviceType->id,
                        'technician_id' => $tech->id,
                        'bay_id' => $bay->id,
                        'status' => 'CONFIRMED',
                        'start_at' => $startUtc,
                        'end_at' => $endUtc,
                        'version' => 1,
                        'source' => 'STAFF',
                        'reminder_opt_in' => true,
                        'created_by_user_id' => $advisor->id,
                        'created_at' => $nowUtc,
                        'updated_at' => $nowUtc,
                    ];

                    $pendingAllocations[] = [
                        'appointment_id' => $aptId,
                        'dealership_id' => $dealership->id,
                        'allocations' => [
                            ['resource_type' => 'TECHNICIAN', 'resource_id' => $tech->id, 'start_at' => $startUtc, 'end_at' => $endUtc],
                            ['resource_type' => 'BAY', 'resource_id' => $bay->id, 'start_at' => $startUtc, 'end_at' => $endUtc],
                            ['resource_type' => 'VEHICLE', 'resource_id' => $veh->id, 'start_at' => $startUtc, 'end_at' => $endUtc],
                        ],
                    ];
                }
            }

            foreach (array_chunk($appointmentsData, 50) as $chunk) {
                DB::table('appointments')->insert($chunk);
            }

            foreach ($pendingAllocations as $item) {
                $allocationRepo->insertFor($item['appointment_id'], $item['dealership_id'], $item['allocations'], $now);
            }

            $this->assertSame(200, DB::table('appointments')->where('dealership_id', $dealership->id)->count());
            $this->assertSame(600, DB::table('resource_allocations')->where('dealership_id', $dealership->id)->count());

            // Prepare 50 customers with vehicles for the 50 sequential requests
            $testVehicles = [];
            for ($i = 1; $i <= 50; $i++) {
                $user = User::factory()->customer()->create([
                    'email' => "perf_test_cust_{$i}@example.com",
                ]);
                $customer = Customer::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'full_name' => "Test Customer {$i}",
                    'phone' => sprintf('090700%04d', $i),
                ]);
                $testVehicles[] = Vehicle::create([
                    'id' => (string) Str::uuid(),
                    'customer_id' => $customer->id,
                    'licence_plate' => sprintf('51T-%03d.%02d', intdiv($i, 100), $i % 100),
                    'plate_key' => sprintf('51T%05d', $i),
                    'make' => 'Honda',
                    'model' => 'Civic',
                    'year' => 2022,
                ]);
            }

            // Execute 50 sequential POST /appointments (different times)
            $durations = [];
            for ($k = 0; $k < 50; $k++) {
                $h = 7 + intdiv($k * 15, 60);
                $m = ($k * 15) % 60;
                $timeStr = sprintf('2026-11-02T%02d:%02d:00+07:00', $h, $m);
                $veh = $testVehicles[$k];

                $key = (string) Str::uuid();

                $t0 = hrtime(true);
                $response = $this->actingAs($advisor)->postJson('/api/v1/appointments', [
                    'customerId' => $veh->customer_id,
                    'vehicleId' => $veh->id,
                    'serviceTypeId' => $serviceType->id,
                    'desiredStart' => $timeStr,
                ], [
                    'Idempotency-Key' => $key,
                ]);
                $t1 = hrtime(true);

                $response->assertStatus(201);
                $durations[] = ($t1 - $t0) / 1e6;
            }

            $this->assertCount(50, $durations);

            // Compute p95
            sort($durations);
            $p95Index = (int) ceil(0.95 * count($durations)) - 1;
            $p95 = $durations[$p95Index];

            $this->assertLessThan(
                300,
                $p95,
                sprintf(
                    'Expected POST /appointments p95 < 300 ms, got %.2f ms (min: %.2f ms, median: %.2f ms, p95: %.2f ms, max: %.2f ms)',
                    $p95,
                    $durations[0],
                    $durations[25],
                    $p95,
                    $durations[49]
                )
            );

            $this->assertNoOverlappingAllocations();
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
