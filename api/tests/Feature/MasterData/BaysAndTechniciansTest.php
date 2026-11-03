<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\BayType;
use App\Models\Dealership;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\World;
use Tests\TestCase;

class BaysAndTechniciansTest extends TestCase
{
    use RefreshDatabase;

    public function test_AC_01_3_customer_cannot_read_bays_or_technicians(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $customerUser = $world->customerWithVehicle()['user'];

        // Customer calls GET /dealerships/{d}/bays -> 403 FORBIDDEN
        $baysResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$dealership->id}/bays");

        $baysResponse->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('status', 403);

        // Customer calls GET /dealerships/{d}/technicians -> 403 FORBIDDEN
        $techsResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$dealership->id}/technicians");

        $techsResponse->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN')
            ->assertJsonPath('status', 403);
    }

    public function test_staff_can_read_bays_with_supported_service_type_ids_sorted_by_code(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $advisor = $world->advisor();

        $oilChange = $world->serviceType('OIL_CHANGE');
        $maint20k = $world->serviceType('MAINT_20K');
        $evBattery = $world->serviceType('EV_BATTERY');

        // Add an inactive QUICK service to verify inactive services are excluded from supportedServiceTypeIds
        ServiceType::create([
            'dealership_id' => $dealership->id,
            'code' => 'TIRE_ROTATION',
            'name' => 'Tire rotation',
            'description' => 'Inactive seasonal service',
            'duration_minutes' => 30,
            'required_level' => 1,
            'required_bay_type' => BayType::QUICK,
            'active' => false,
        ]);

        $response = $this->actingAs($advisor)
            ->getJson("/api/v1/dealerships/{$dealership->id}/bays");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name', 'bayType', 'active', 'supportedServiceTypeIds'],
                ],
            ])
            ->assertJsonCount(4, 'data');

        $data = $response->json('data');

        // Verify sorted by code
        $codes = array_column($data, 'code');
        $this->assertSame(['K1', 'K2', 'K3', 'KEV'], $codes);

        // K1 and K2 are QUICK bays -> supportedServiceTypeIds contains active QUICK service (OIL_CHANGE)
        $this->assertSame('QUICK', $data[0]['bayType']);
        $this->assertSame([$oilChange->id], $data[0]['supportedServiceTypeIds']);
        $this->assertTrue($data[0]['active']);

        $this->assertSame('QUICK', $data[1]['bayType']);
        $this->assertSame([$oilChange->id], $data[1]['supportedServiceTypeIds']);
        $this->assertTrue($data[1]['active']);

        // K3 is STANDARD bay -> supportedServiceTypeIds contains active STANDARD service (MAINT_20K)
        $this->assertSame('STANDARD', $data[2]['bayType']);
        $this->assertSame([$maint20k->id], $data[2]['supportedServiceTypeIds']);
        $this->assertTrue($data[2]['active']);

        // KEV is EV bay -> supportedServiceTypeIds contains active EV service (EV_BATTERY)
        $this->assertSame('EV', $data[3]['bayType']);
        $this->assertSame([$evBattery->id], $data[3]['supportedServiceTypeIds']);
        $this->assertTrue($data[3]['active']);
    }

    public function test_staff_can_read_technicians_including_inactive_sorted_by_name(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;
        $admin = $world->admin();

        $response = $this->actingAs($admin)
            ->getJson("/api/v1/dealerships/{$dealership->id}/technicians");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'name', 'level', 'active'],
                ],
            ])
            ->assertJsonCount(5, 'data');

        $data = $response->json('data');

        // Verify sorted by name
        $names = array_column($data, 'name');
        $this->assertSame([
            'Huỳnh Tấn Phát',
            'Lý Hoàng Dũng',
            'Ngô Bảo Em',
            'Trần Minh An',
            'Võ Thị Chi',
        ], $names);

        // Verify Huỳnh Tấn Phát is level 1 and active = false
        $this->assertSame('Huỳnh Tấn Phát', $data[0]['name']);
        $this->assertSame(1, $data[0]['level']);
        $this->assertFalse($data[0]['active']);

        // Verify Ngô Bảo Em is level 6 and active = true
        $this->assertSame('Ngô Bảo Em', $data[2]['name']);
        $this->assertSame(6, $data[2]['level']);
        $this->assertTrue($data[2]['active']);
    }

    public function test_foreign_dealership_returns_404_dealership_not_found_for_staff(): void
    {
        $world1 = World::saigonD7();
        $advisor1 = $world1->advisor();

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);

        $baysResponse = $this->actingAs($advisor1)
            ->getJson("/api/v1/dealerships/{$dealership2->id}/bays");

        $baysResponse->assertStatus(404)
            ->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');

        $techsResponse = $this->actingAs($advisor1)
            ->getJson("/api/v1/dealerships/{$dealership2->id}/technicians");

        $techsResponse->assertStatus(404)
            ->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND');
    }

    public function test_unauthenticated_request_is_401(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;

        $this->getJson("/api/v1/dealerships/{$dealership->id}/bays")
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->getJson("/api/v1/dealerships/{$dealership->id}/technicians")
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
