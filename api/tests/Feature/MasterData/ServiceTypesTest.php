<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\BayType;
use App\Models\ServiceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\World;
use Tests\TestCase;

class ServiceTypesTest extends TestCase
{
    use RefreshDatabase;

    public function test_AC_01_1_seeded_service_types_are_returned(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();

        $response = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$world->dealership->id}/service-types");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'name',
                        'description',
                        'durationMinutes',
                        'requiredLevel',
                        'requiredBayType',
                        'active',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertCount(3, $data);

        $validBayTypes = array_map(fn (BayType $case) => $case->value, BayType::cases());

        foreach ($data as $item) {
            $this->assertGreaterThan(0, $item['durationMinutes']);
            $this->assertSame(0, $item['durationMinutes'] % 15);
            $this->assertGreaterThanOrEqual(1, $item['requiredLevel']);
            $this->assertLessThanOrEqual(7, $item['requiredLevel']);
            $this->assertContains($item['requiredBayType'], $validBayTypes);
            $this->assertTrue($item['active']);
        }

        $codes = array_column($data, 'code');
        $this->assertEqualsCanonicalizing(['OIL_CHANGE', 'MAINT_20K', 'EV_BATTERY'], $codes);
    }

    public function test_AC_01_4_inactive_service_hidden_from_customers(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $customerUser = $world->customerWithVehicle()['user'];

        // Add an inactive service type
        ServiceType::create([
            'dealership_id' => $world->dealership->id,
            'code' => 'TIRE_ROTATION',
            'name' => 'Tire Rotation',
            'description' => 'Rotate tires',
            'duration_minutes' => 30,
            'required_level' => 1,
            'required_bay_type' => BayType::QUICK->value,
            'active' => false,
        ]);

        // Customer sees only the 3 active services
        $customerResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/service-types");

        $customerResponse->assertOk();
        $customerData = $customerResponse->json('data');
        $this->assertCount(3, $customerData);
        foreach ($customerData as $service) {
            $this->assertTrue($service['active']);
            $this->assertNotSame('TIRE_ROTATION', $service['code']);
        }

        // Admin sees all 4 services including the inactive one
        $adminResponse = $this->actingAs($admin)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/service-types");

        $adminResponse->assertOk();
        $adminData = $adminResponse->json('data');
        $this->assertCount(4, $adminData);

        $inactiveService = null;
        foreach ($adminData as $service) {
            if ($service['code'] === 'TIRE_ROTATION') {
                $inactiveService = $service;
                break;
            }
        }
        $this->assertNotNull($inactiveService);
        $this->assertFalse($inactiveService['active']);
    }

    public function test_customer_cannot_include_inactive(): void
    {
        $world = World::saigonD7();
        $customerUser = $world->customerWithVehicle()['user'];

        ServiceType::create([
            'dealership_id' => $world->dealership->id,
            'code' => 'TIRE_ROTATION',
            'name' => 'Tire Rotation',
            'description' => 'Rotate tires',
            'duration_minutes' => 30,
            'required_level' => 1,
            'required_bay_type' => BayType::QUICK->value,
            'active' => false,
        ]);

        // TC-01-13: Customer passes ?includeInactive=true, still only active services are returned
        $response = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$world->dealership->id}/service-types?includeInactive=true");

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(3, $data);

        $codes = array_column($data, 'code');
        $this->assertNotContains('TIRE_ROTATION', $codes);
    }
}
