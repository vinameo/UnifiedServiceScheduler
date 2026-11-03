<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\BayType;
use App\Domain\MasterData\Repositories\MasterDataRepositoryInterface;
use App\Domain\MasterData\Services\UpcomingAssignmentsReader;
use App\Models\Dealership;
use App\Models\ServiceType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\World;
use Tests\TestCase;

class MasterDataAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * TC-01-15 (M1): Admin views Master data: 4 tabs with expected counts
     * - Service types (3 items)
     * - Bays (4 items)
     * - Technicians (5 items)
     * - Opening hours (6 days configured)
     */
    public function test_TC_01_15_M1_admin_reads_master_data_catalogue(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;

        // Dealership details + Opening hours
        $dResponse = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}");
        $dResponse->assertOk()
            ->assertJsonPath('data.id', $dealership->id)
            ->assertJsonPath('data.name', 'Saigon Auto')
            ->assertJsonPath('data.branchName', 'District 7')
            ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.slotStepMinutes', 15);

        $openingHours = $dResponse->json('data.openingHours');
        $this->assertIsArray($openingHours);
        $this->assertCount(6, $openingHours);

        // Service types tab: 3 seeded types
        $stResponse = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}/service-types");
        $stResponse->assertOk();
        $stData = $stResponse->json('data');
        $this->assertCount(3, $stData);
        $this->assertEqualsCanonicalizing(['OIL_CHANGE', 'MAINT_20K', 'EV_BATTERY'], array_column($stData, 'code'));

        // Bays tab: 4 seeded bays
        $baysResponse = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}/bays");
        $baysResponse->assertOk();
        $baysData = $baysResponse->json('data');
        $this->assertCount(4, $baysData);
        $this->assertEqualsCanonicalizing(['K1', 'K2', 'K3', 'KEV'], array_column($baysData, 'code'));

        // Technicians tab: 5 seeded technicians
        $techResponse = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}/technicians");
        $techResponse->assertOk();
        $techData = $techResponse->json('data');
        $this->assertCount(5, $techData);
    }

    /**
     * TC-01-15 (M2): Technicians tab: columns Technician, Level, Status, Action.
     * Huỳnh Tấn Phát: Level 1, Not taking bookings (active: false).
     * No shift or skills columns.
     */
    public function test_TC_01_15_M2_technicians_tab_columns_and_huynh_tan_phat_initial_state(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;

        $response = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}/technicians");
        $response->assertOk();

        /** @var list<array<string, mixed>> $technicians */
        $technicians = (array) $response->json('data');
        $this->assertCount(5, $technicians);

        $phat = collect($technicians)->firstWhere('name', 'Huỳnh Tấn Phát');
        $this->assertNotNull($phat);
        $this->assertSame(1, $phat['level']);
        $this->assertFalse($phat['active']); // "Not taking bookings" -> maps to "Enable" action

        // Other technicians are active
        $expectedActive = [
            'Trần Minh An' => 2,
            'Lý Hoàng Dũng' => 3,
            'Võ Thị Chi' => 4,
            'Ngô Bảo Em' => 6,
        ];

        foreach ($expectedActive as $name => $expectedLevel) {
            $tech = collect($technicians)->firstWhere('name', $name);
            $this->assertNotNull($tech, "Technician {$name} should exist");
            $this->assertSame($expectedLevel, $tech['level']);
            $this->assertTrue($tech['active'], "Technician {$name} should be active");
            $this->assertArrayNotHasKey('shift', $tech);
            $this->assertArrayNotHasKey('skills', $tech);
        }
    }

    /**
     * TC-01-15 (M3): Opening hours tab: Monday to Friday 08:00–17:00, Saturday 08:00–12:00,
     * Sunday closed, Asia/Ho_Chi_Minh (UTC+7), 15 min step.
     */
    public function test_TC_01_15_M3_opening_hours_tab_mon_to_sat_open_sunday_closed(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;

        $response = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}");
        $response->assertOk();

        $data = $response->json('data');
        $this->assertSame('Asia/Ho_Chi_Minh', $data['timezone']);
        $this->assertSame(15, $data['slotStepMinutes']);

        /** @var list<array<string, mixed>> $openingHoursList */
        $openingHoursList = (array) $data['openingHours'];
        $openingHours = collect($openingHoursList);
        $this->assertCount(6, $openingHours);

        // Weekdays 1-5 (Mon-Fri) are 08:00-17:00
        for ($weekday = 1; $weekday <= 5; $weekday++) {
            $day = $openingHours->firstWhere('weekday', $weekday);
            $this->assertNotNull($day, "Weekday {$weekday} must have opening hours");
            $this->assertSame('08:00', $day['opensAt']);
            $this->assertSame('17:00', $day['closesAt']);
        }

        // Weekday 6 (Sat) is 08:00-12:00
        $sat = $openingHours->firstWhere('weekday', 6);
        $this->assertNotNull($sat, 'Saturday must have opening hours');
        $this->assertSame('08:00', $sat['opensAt']);
        $this->assertSame('12:00', $sat['closesAt']);

        // Weekday 7 (Sunday) is closed (absent from openingHours)
        $sun = $openingHours->firstWhere('weekday', 7);
        $this->assertNull($sun, 'Sunday must be closed (not in openingHours list)');
    }

    /**
     * TC-01-15 (M4): Advisor and customer access restrictions.
     * Advisor cannot mutate technician status (403 FORBIDDEN).
     * Customer cannot read bays or technicians (403 FORBIDDEN).
     * Advisor cannot query foreign dealership (404 DEALERSHIP_NOT_FOUND).
     */
    public function test_TC_01_15_M4_advisor_and_customer_role_restrictions(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        $customer = $world->customerWithVehicle()['user'];
        $dealership = $world->dealership;
        $chi = $world->technician('Võ Thị Chi');

        $foreignDealership = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);

        // Advisor cannot update technician status
        $respAdvisorDisable = $this->actingAs($advisor)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$chi->id}/status", [
                'active' => false,
            ]);
        $respAdvisorDisable->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // Customer cannot read bays
        $respCustomerBays = $this->actingAs($customer)
            ->getJson("/api/v1/dealerships/{$dealership->id}/bays");
        $respCustomerBays->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // Customer cannot read technicians
        $respCustomerTechs = $this->actingAs($customer)
            ->getJson("/api/v1/dealerships/{$dealership->id}/technicians");
        $respCustomerTechs->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');

        // Advisor accessing foreign dealership gets 404 DEALERSHIP_NOT_FOUND
        $respForeign = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$foreignDealership->id}/bays");
        $respForeign->assertStatus(404)->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');
    }

    /**
     * TC-01-15 (M5): Admin disables Võ Thị Chi (no bookings), then enables her.
     * Returns 200, updates active flag in database and repository snapshot.
     */
    public function test_TC_01_15_M5_admin_disables_vo_thi_chi_then_enables_her(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;
        $chi = $world->technician('Võ Thị Chi');

        $this->assertTrue($chi->active);

        // 1. Disable Chi
        $disableResp = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$chi->id}/status", [
                'active' => false,
            ]);

        $disableResp->assertOk()
            ->assertJsonPath('data.id', $chi->id)
            ->assertJsonPath('data.name', 'Võ Thị Chi')
            ->assertJsonPath('data.active', false);

        $freshChi = $chi->fresh();
        $this->assertNotNull($freshChi);
        $this->assertFalse($freshChi->active);

        $repo = app(MasterDataRepositoryInterface::class);
        $techInRepo = collect($repo->technicians($dealership->id))->firstWhere('id', $chi->id);
        $this->assertNotNull($techInRepo);
        $this->assertFalse($techInRepo->active);

        // 2. Enable Chi
        $enableResp = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$chi->id}/status", [
                'active' => true,
            ]);

        $enableResp->assertOk()
            ->assertJsonPath('data.id', $chi->id)
            ->assertJsonPath('data.name', 'Võ Thị Chi')
            ->assertJsonPath('data.active', true);

        $freshChiAfter = $chi->fresh();
        $this->assertNotNull($freshChiAfter);
        $this->assertTrue($freshChiAfter->active);

        $techInRepoAfter = collect($repo->technicians($dealership->id))->firstWhere('id', $chi->id);
        $this->assertNotNull($techInRepoAfter);
        $this->assertTrue($techInRepoAfter->active);
    }

    /**
     * TC-01-15 (M6): Admin tries to disable technician with upcoming appointments.
     * Returns 409 TECHNICIAN_HAS_BOOKINGS with reference; technician stays active.
     */
    public function test_TC_01_15_M6_upcoming_booking_blocks_disabling_ly_hoang_dung(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;
        $dung = $world->technician('Lý Hoàng Dũng');

        $this->assertTrue($dung->active);

        // Bind mock reader reporting 1 upcoming appointment for Lý Hoàng Dũng
        $fakeReader = new class implements UpcomingAssignmentsReader
        {
            public function count(string $technicianId, CarbonImmutable $now): int
            {
                return 1;
            }

            public function preview(string $technicianId, CarbonImmutable $now, int $limit = 20): array
            {
                return [
                    [
                        'id' => '01930000-0000-7000-8000-000000000099',
                        'reference' => 'APT-20261103-0012',
                        'start' => '2026-11-03T09:00:00+07:00',
                        'end' => '2026-11-03T10:00:00+07:00',
                        'customerName' => 'Nguyễn Văn Hùng',
                    ],
                ];
            }
        };

        $this->app->instance(UpcomingAssignmentsReader::class, $fakeReader);

        $response = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$dung->id}/status", [
                'active' => false,
            ]);

        $response->assertStatus(409)
            ->assertJsonPath('code', 'TECHNICIAN_HAS_BOOKINGS')
            ->assertJsonPath('status', 409)
            ->assertJsonPath('count', 1)
            ->assertJsonPath('appointments.0.reference', 'APT-20261103-0012')
            ->assertJsonPath('appointments.0.customerName', 'Nguyễn Văn Hùng');

        // Dũng remains active ("Working")
        $freshDung = $dung->fresh();
        $this->assertNotNull($freshDung);
        $this->assertTrue($freshDung->active);
    }

    /**
     * TC-01-15: Complete end-to-end master data lifecycle across customer, advisor, and admin.
     */
    public function test_TC_01_15_end_to_end_master_data_lifecycle(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $customer = $world->customerWithVehicle()['user'];
        $advisor = $world->advisor();
        $admin = $world->admin();

        // 1. Unauthenticated request is rejected
        $this->getJson("/api/v1/dealerships/{$dealership->id}/service-types")
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        // 2. Customer lists dealerships (sees all dealerships, here SGA-D7)
        $custDealerships = $this->actingAs($customer)->getJson('/api/v1/dealerships');
        $custDealerships->assertOk();
        $this->assertNotEmpty($custDealerships->json('data'));

        // Customer sees 3 active service types
        $custServices = $this->actingAs($customer)->getJson("/api/v1/dealerships/{$dealership->id}/service-types");
        $custServices->assertOk();
        $this->assertCount(3, $custServices->json('data'));

        // Adding inactive service: visible to admin, hidden from customer
        ServiceType::create([
            'dealership_id' => $dealership->id,
            'code' => 'TIRE_ROTATION',
            'name' => 'Tire Rotation',
            'duration_minutes' => 30,
            'required_level' => 1,
            'required_bay_type' => BayType::QUICK->value,
            'active' => false,
        ]);

        $custServicesAfter = $this->actingAs($customer)->getJson("/api/v1/dealerships/{$dealership->id}/service-types");
        $custServicesAfter->assertOk();
        $this->assertCount(3, $custServicesAfter->json('data'));

        $adminServices = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}/service-types");
        $adminServices->assertOk();
        $this->assertCount(4, $adminServices->json('data'));

        // 3. Advisor lists dealerships: scoped to own dealership
        $advDealerships = $this->actingAs($advisor)->getJson('/api/v1/dealerships');
        $advDealerships->assertOk();
        $this->assertCount(1, $advDealerships->json('data'));
        $this->assertSame($dealership->id, $advDealerships->json('data.0.id'));

        // Advisor reads bays and technicians
        $advBays = $this->actingAs($advisor)->getJson("/api/v1/dealerships/{$dealership->id}/bays");
        $advBays->assertOk();
        $this->assertCount(4, $advBays->json('data'));

        $advTechs = $this->actingAs($advisor)->getJson("/api/v1/dealerships/{$dealership->id}/technicians");
        $advTechs->assertOk();
        $this->assertCount(5, $advTechs->json('data'));

        // 4. Admin toggles technician status (Huỳnh Tấn Phát was inactive -> enable)
        $phat = $world->technician('Huỳnh Tấn Phát');
        $this->assertFalse($phat->active);

        $adminEnablePhat = $this->actingAs($admin)
            ->putJson("/api/v1/dealerships/{$dealership->id}/technicians/{$phat->id}/status", [
                'active' => true,
            ]);
        $adminEnablePhat->assertOk()->assertJsonPath('data.active', true);
        $freshPhat = $phat->fresh();
        $this->assertNotNull($freshPhat);
        $this->assertTrue($freshPhat->active);
    }
}
