<?php

namespace Tests\Feature\Booking\Availability;

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
use Tests\TestCase;

#[Group('performance')]
/**
 * @group performance
 */
class AvailabilityPerformanceTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * TC-06-13 / AC-06.16 / NFR-02 / M-06.1:
     * Given Dealership with 20 bays, 30 technicians, 200 bookings that day;
     * when 30 × GET availability;
     * then p95 < 300 ms.
     */
    public function test_TC_06_13_get_availability_p95_under_300ms(): void
    {
        $now = CarbonImmutable::parse('2026-11-02 06:00:00', 'Asia/Ho_Chi_Minh');
        CarbonImmutable::setTestNow($now);

        try {
            $dealership = Dealership::create([
                'id' => (string) Str::uuid(),
                'code' => 'SGA-PERF-AVAIL',
                'name' => 'Saigon Performance Dealership',
                'branch_name' => 'Benchmark',
                'timezone' => 'Asia/Ho_Chi_Minh',
                'phone' => '028 3812 3456',
            ]);

            // Opening hours on Monday (weekday 1): 08:00 to 18:00
            DealershipOpeningHour::create([
                'dealership_id' => $dealership->id,
                'weekday' => 1,
                'opens_at' => '08:00:00',
                'closes_at' => '18:00:00',
            ]);

            $serviceType = ServiceType::create([
                'id' => (string) Str::uuid(),
                'dealership_id' => $dealership->id,
                'code' => 'OIL_PERF',
                'name' => 'Quick Oil Service',
                'description' => 'Fast oil change for performance test',
                'duration_minutes' => 60,
                'required_level' => 1,
                'required_bay_type' => BayType::QUICK->value,
                'active' => true,
            ]);

            // 20 bays: 12 QUICK, 6 STANDARD, 2 EV
            $bays = [];
            for ($i = 1; $i <= 20; $i++) {
                $bayType = match (true) {
                    $i <= 12 => BayType::QUICK->value,
                    $i <= 18 => BayType::STANDARD->value,
                    default => BayType::EV->value,
                };
                $bays[] = Bay::create([
                    'id' => (string) Str::uuid(),
                    'dealership_id' => $dealership->id,
                    'code' => sprintf('BAY-%02d', $i),
                    'name' => sprintf('Bay %02d', $i),
                    'bay_type' => $bayType,
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
                    'email' => "avail_perf_cust_{$c}@example.com",
                ]);
                $customer = Customer::create([
                    'id' => (string) Str::uuid(),
                    'user_id' => $user->id,
                    'full_name' => "Customer {$c}",
                    'phone' => sprintf('090400%04d', $c),
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

            // Populate 200 existing bookings across 20 distinct times (10 bays and 10 techs used per time)
            $appointmentsData = [];
            $nowUtc = $now->setTimezone('UTC')->toIso8601String();
            $allocationRepo = app(ResourceAllocationRepositoryInterface::class);
            $pendingAllocations = [];

            for ($s = 0; $s < 20; $s++) {
                $h = intdiv($s, 2) + 8;
                $m = ($s % 2) * 30;
                $startLocal = CarbonImmutable::parse(sprintf('2026-11-02 %02d:%02d:00', $h, $m), 'Asia/Ho_Chi_Minh');
                $endLocal = $startLocal->addMinutes(30);
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
                        'reference' => sprintf('APT-AVAIL-PERF-%04d', $idx + 1),
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

            // Warm-up run
            $warmup = $this->actingAs($advisor)->getJson(
                "/api/v1/dealerships/{$dealership->id}/availability?date=2026-11-02&serviceTypeId={$serviceType->id}"
            );
            $warmup->assertOk();

            // Execute 30 sequential GET /availability calls
            $durations = [];
            for ($k = 0; $k < 30; $k++) {
                $t0 = hrtime(true);
                $response = $this->actingAs($advisor)->getJson(
                    "/api/v1/dealerships/{$dealership->id}/availability?date=2026-11-02&serviceTypeId={$serviceType->id}"
                );
                $t1 = hrtime(true);

                $response->assertOk();
                $durations[] = ($t1 - $t0) / 1e6;
            }

            $this->assertCount(30, $durations);

            // Compute p95 latency
            sort($durations);
            $p95Index = (int) ceil(0.95 * count($durations)) - 1;
            $p95 = $durations[$p95Index];
            $min = $durations[0];
            $median = $durations[15];
            $max = $durations[29];

            $this->assertLessThan(
                300.0,
                $p95,
                sprintf(
                    'Expected GET /availability p95 < 300 ms, got %.2f ms (min: %.2f ms, median: %.2f ms, p95: %.2f ms, max: %.2f ms)',
                    $p95,
                    $min,
                    $median,
                    $p95,
                    $max
                )
            );
        } finally {
            CarbonImmutable::setTestNow();
        }
    }
}
