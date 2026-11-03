<?php

namespace Tests\Feature\MasterData;

use App\Models\Dealership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->assertSame([$dealership1->id, $dealership2->id], $dealershipIds);

        // Customer calls GET /dealerships/{D2}/service-types -> 200
        $servicesResponse = $this->actingAs($customerUser)
            ->getJson("/api/v1/dealerships/{$dealership2->id}/service-types");

        $servicesResponse->assertOk();
        $servicesData = $servicesResponse->json('data');
        $this->assertCount(3, $servicesData);
    }

    public function test_AC_01_5_staff_list_only_their_dealership(): void
    {
        $world1 = World::saigonD7();
        $dealership1 = $world1->dealership;

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
            'branch_name' => 'Binh Thanh',
            'timezone' => 'Asia/Ho_Chi_Minh',
            'phone' => '028 3812 7890',
        ]);
        $world2 = World::forDealership($dealership2);

        $advisor1 = $world1->advisor();
        $foreman1 = $world1->foreman();
        $admin1 = $world1->admin();
        $advisor2 = $world2->advisor();

        // 1. Advisor of D1 sees only D1
        $advisorResponse = $this->actingAs($advisor1)->getJson('/api/v1/dealerships');
        $advisorResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dealership1->id)
            ->assertJsonPath('data.0.code', 'SGA-D7')
            ->assertJsonPath('data.0.name', 'Saigon Auto')
            ->assertJsonPath('data.0.branchName', 'District 7')
            ->assertJsonPath('data.0.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.0.phone', '028 3812 3456')
            ->assertJsonPath('data.0.slotStepMinutes', 15)
            ->assertJsonStructure([
                'data' => [
                    [
                        'id',
                        'code',
                        'name',
                        'branchName',
                        'timezone',
                        'phone',
                        'slotStepMinutes',
                        'openingHours' => [
                            '*' => ['weekday', 'opensAt', 'closesAt'],
                        ],
                    ],
                ],
            ]);

        $advisorData = $advisorResponse->json('data.0');
        $this->assertCount(6, $advisorData['openingHours']); // Mon-Sat open, Sun closed

        // 2. Workshop Foreman of D1 sees only D1
        $foremanResponse = $this->actingAs($foreman1)->getJson('/api/v1/dealerships');
        $foremanResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dealership1->id);

        // 3. Admin of D1 sees only D1
        $adminResponse = $this->actingAs($admin1)->getJson('/api/v1/dealerships');
        $adminResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dealership1->id);

        // 4. Advisor of D2 sees only D2
        $advisor2Response = $this->actingAs($advisor2)->getJson('/api/v1/dealerships');
        $advisor2Response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dealership2->id);

        // 5. Adversarial input: unknown query param ?dealershipId=<other> is ignored (API §B4)
        $adversarialResponse = $this->actingAs($advisor1)->getJson("/api/v1/dealerships?dealershipId={$dealership2->id}");
        $adversarialResponse->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $dealership1->id);
    }

    public function test_unauthenticated_request_to_list_dealerships_is_401(): void
    {
        $response = $this->getJson('/api/v1/dealerships');
        $response->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    /**
     * @return array<string, array{string}>
     */
    public static function scopedRoutesProvider(): array
    {
        return [
            'dealership details' => ['/api/v1/dealerships/{d}'],
            'service types' => ['/api/v1/dealerships/{d}/service-types'],
            'bays' => ['/api/v1/dealerships/{d}/bays'],
            'technicians' => ['/api/v1/dealerships/{d}/technicians'],
        ];
    }

    #[DataProvider('scopedRoutesProvider')]
    public function test_AC_01_2_other_dealership_is_404(string $routeTemplate): void
    {
        $world1 = World::saigonD7();
        $admin = $world1->admin();

        $dealership2 = Dealership::factory()->create([
            'code' => 'SGA-BTH',
            'name' => 'Saigon Auto Binh Thanh',
        ]);
        World::forDealership($dealership2);

        $url = str_replace('{d}', $dealership2->id, $routeTemplate);

        $response = $this->actingAs($admin)->getJson($url);

        $response->assertStatus(404)
            ->assertJsonPath('code', 'DEALERSHIP_NOT_FOUND')
            ->assertJsonPath('status', 404);
    }

    public function test_admin_reads_own_dealership_details_with_opening_hours(): void
    {
        $world = World::saigonD7();
        $admin = $world->admin();
        $dealership = $world->dealership;

        $response = $this->actingAs($admin)->getJson("/api/v1/dealerships/{$dealership->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $dealership->id)
            ->assertJsonPath('data.code', 'SGA-D7')
            ->assertJsonPath('data.name', 'Saigon Auto')
            ->assertJsonPath('data.branchName', 'District 7')
            ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh')
            ->assertJsonPath('data.phone', '028 3812 3456')
            ->assertJsonPath('data.slotStepMinutes', 15)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'code',
                    'name',
                    'branchName',
                    'timezone',
                    'phone',
                    'slotStepMinutes',
                    'openingHours' => [
                        '*' => ['weekday', 'opensAt', 'closesAt'],
                    ],
                ],
            ]);

        $this->assertCount(6, $response->json('data.openingHours'));
    }

    public function test_customer_reads_dealership_details_with_opening_hours(): void
    {
        $world = World::saigonD7();
        $customerUser = $world->customerWithVehicle()['user'];
        $dealership = $world->dealership;

        $response = $this->actingAs($customerUser)->getJson("/api/v1/dealerships/{$dealership->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $dealership->id)
            ->assertJsonPath('data.code', 'SGA-D7');
    }

    public function test_unauthenticated_request_to_dealership_details_is_401(): void
    {
        $world = World::saigonD7();
        $dealership = $world->dealership;

        $response = $this->getJson("/api/v1/dealerships/{$dealership->id}");
        $response->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');
    }
}
