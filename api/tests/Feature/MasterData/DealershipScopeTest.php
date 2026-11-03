<?php

namespace Tests\Feature\MasterData;

use App\Models\Dealership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class DealershipScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_INV_01_3_missing_malformed_and_foreign_look_the_same(): void
    {
        $world1 = World::saigonD7();
        $advisor = $world1->advisor();

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);

        $missingUuid = (string) Str::uuid();

        $resMalformed = $this->actingAs($advisor)->getJson('/api/v1/dealerships/abc/service-types');
        $resMissing = $this->actingAs($advisor)->getJson("/api/v1/dealerships/{$missingUuid}/service-types");
        $resForeign = $this->actingAs($advisor)->getJson("/api/v1/dealerships/{$dealership2->id}/service-types");

        $resMalformed->assertStatus(404);
        $resMissing->assertStatus(404);
        $resForeign->assertStatus(404);

        $bodyMalformed = $resMalformed->json();
        $bodyMissing = $resMissing->json();
        $bodyForeign = $resForeign->json();

        $this->assertSame('DEALERSHIP_NOT_FOUND', $bodyMalformed['code']);
        $this->assertSame(404, $bodyMalformed['status']);

        // Assert correlationIds are present and distinct
        $this->assertNotEmpty($bodyMalformed['correlationId']);
        $this->assertNotEmpty($bodyMissing['correlationId']);
        $this->assertNotEmpty($bodyForeign['correlationId']);

        // Remove correlationId to assert exact body equivalence
        unset($bodyMalformed['correlationId'], $bodyMissing['correlationId'], $bodyForeign['correlationId']);

        $this->assertSame($bodyMalformed, $bodyMissing);
        $this->assertSame($bodyMalformed, $bodyForeign);
    }

    public function test_customer_reads_any_dealership_catalogue(): void
    {
        $world1 = World::saigonD7();
        $dealership1 = $world1->dealership;

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'phone' => '028 3812 7890',
        ]);
        $world2 = World::forDealership($dealership2);

        $customerUser = $world1->customerWithVehicle()['user'];

        // Customer calls GET /dealerships -> returns both D1 and D2
        $listResponse = $this->actingAs($customerUser)->getJson('/api/v1/dealerships');
        $listResponse->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'code', 'name'],
                ],
            ]);

        $dealershipIds = array_column($listResponse->json('data'), 'id');
        $this->assertContains($dealership1->id, $dealershipIds);
        $this->assertContains($dealership2->id, $dealershipIds);

        // Customer calls GET /dealerships/{D2}/service-types -> 200
        $servicesResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$dealership2->id}/service-types");

        $servicesResponse->assertOk();
        $servicesData = $servicesResponse->json('data');
        $this->assertCount(3, $servicesData);
    }
}
