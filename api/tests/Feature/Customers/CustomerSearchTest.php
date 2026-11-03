<?php

namespace Tests\Feature\Customers;

use App\Domain\Auth\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class CustomerSearchTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_02_15_search_rules(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        // Create Customer Nguyễn Văn Hùng
        $hungUser = User::create([
            'name' => 'Nguyễn Văn Hùng',
            'email' => 'hung@example.test',
            'password' => Hash::make('Password123!'),
            'role' => Role::CUSTOMER->value,
            'dealership_id' => null,
        ]);
        $hungCustomer = Customer::create([
            'id' => (string) Str::uuid(),
            'user_id' => $hungUser->id,
            'full_name' => 'Nguyễn Văn Hùng',
            'phone' => '0903123218',
        ]);

        // 1. Advisor queries 'hu' (< 3 chars) -> 400 VALIDATION_ERROR
        $resShort = $this->actingAs($advisor)->getJson('/api/v1/customers?query=hu');
        $resShort->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // 2. Advisor queries 'hùng' -> matches Hùng
        $resName = $this->actingAs($advisor)->getJson('/api/v1/customers?query=hùng');
        $resName->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $hungCustomer->id)
            ->assertJsonPath('data.0.fullName', 'Nguyễn Văn Hùng')
            ->assertJsonPath('data.0.phone', '0903123218')
            ->assertJsonPath('data.0.email', 'hung@example.test');

        // 3. Advisor queries '0903' -> matches Hùng by phone prefix
        $resPhone = $this->actingAs($advisor)->getJson('/api/v1/customers?query=0903');
        $resPhone->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $hungCustomer->id);

        // 4. Advisor queries 'hung@' -> matches Hùng by email prefix
        $resEmail = $this->actingAs($advisor)->getJson('/api/v1/customers?query=hung@');
        $resEmail->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $hungCustomer->id);

        // 5. Customer queries 'hùng' -> 403 FORBIDDEN
        $resForbidden = $this->actingAs($hungUser)->getJson('/api/v1/customers?query=hùng');
        $resForbidden->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_like_wildcards_are_escaped(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        $user = User::create([
            'name' => '100% Auto',
            'email' => 'auto100@example.test',
            'password' => Hash::make('Password123!'),
            'role' => Role::CUSTOMER->value,
            'dealership_id' => null,
        ]);
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => '100% Auto',
            'phone' => '0903999888',
        ]);

        // '%%%' should match literal %%%, not acts as wildcard for anything -> 0 matches
        $resWildcard = $this->actingAs($advisor)->getJson('/api/v1/customers?query=%%%');
        $resWildcard->assertStatus(200)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');

        // '100%' matches literal '100%' -> matches 100% Auto
        $resLiteral = $this->actingAs($advisor)->getJson('/api/v1/customers?query=100%');
        $resLiteral->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $customer->id)
            ->assertJsonPath('data.0.fullName', '100% Auto');
    }

    public function test_D_02_15_total_is_the_real_count(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();

        // Seed 25 customers named "Nguyễn ..."
        for ($i = 1; $i <= 25; $i++) {
            $user = User::create([
                'name' => sprintf('Nguyễn Văn %02d', $i),
                'email' => sprintf('nguyen%02d@example.test', $i),
                'password' => Hash::make('Password123!'),
                'role' => Role::CUSTOMER->value,
                'dealership_id' => null,
            ]);
            Customer::create([
                'id' => (string) Str::uuid(),
                'user_id' => $user->id,
                'full_name' => sprintf('Nguyễn Văn %02d', $i),
                'phone' => sprintf('09030000%02d', $i),
            ]);
        }

        $response = $this->actingAs($advisor)->getJson('/api/v1/customers?query=nguy');

        $response->assertStatus(200)
            ->assertJsonPath('meta.total', 25)
            ->assertJsonCount(20, 'data');
    }

    public function test_customer_vehicles_endpoint_rules(): void
    {
        $world = World::saigonD7();
        $advisor = $world->advisor();
        ['customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle(
            null,
            ['make' => 'Toyota', 'model' => 'Vios', 'plate_key' => '51H12345', 'licence_plate' => '51H-123.45']
        );

        // 1. Advisor reads customer's vehicles -> 200
        $res = $this->actingAs($advisor)->getJson("/api/v1/customers/{$customer->id}/vehicles");
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $vehicle->id)
            ->assertJsonPath('data.0.licencePlate', '51H-123.45')
            ->assertJsonPath('data.0.make', 'Toyota')
            ->assertJsonPath('data.0.model', 'Vios');

        // 2. Unknown customer UUID -> 404 CUSTOMER_NOT_FOUND
        $unknownId = (string) Str::uuid();
        $resUnknown = $this->actingAs($advisor)->getJson("/api/v1/customers/{$unknownId}/vehicles");
        $resUnknown->assertStatus(404)
            ->assertJsonPath('code', 'CUSTOMER_NOT_FOUND');

        // 3. Malformed customer ID -> 404 CUSTOMER_NOT_FOUND
        $resMalformed = $this->actingAs($advisor)->getJson('/api/v1/customers/not-a-uuid/vehicles');
        $resMalformed->assertStatus(404)
            ->assertJsonPath('code', 'CUSTOMER_NOT_FOUND');
    }
}
