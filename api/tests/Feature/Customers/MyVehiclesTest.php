<?php

namespace Tests\Feature\Customers;

use App\Domain\Auth\Role;
use App\Models\Customer;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class MyVehiclesTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * @return array{0: User, 1: Customer}
     */
    private function createCustomerUser(string $name, string $email, string $phone): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make('Password123!'),
            'role' => Role::CUSTOMER->value,
            'dealership_id' => null,
        ]);
        $customer = Customer::create([
            'id' => (string) Str::uuid(),
            'user_id' => $user->id,
            'full_name' => $name,
            'phone' => $phone,
        ]);

        return [$user, $customer];
    }

    public function test_AC_02_9_plate_is_unique_across_spellings(): void
    {
        [$user, $customer] = $this->createCustomerUser('Nguyễn Văn Hùng', 'hung@example.test', '0903123218');

        // 1. Customer adds 51G-999.99 -> 201
        $res = $this->actingAs($user)->postJson(
            '/api/v1/me/vehicles',
            [
                'licencePlate' => '51G-999.99',
                'make' => 'Honda',
                'model' => 'City',
                'year' => 2022,
                'vin' => null,
            ],
            ['Idempotency-Key' => (string) Str::uuid()]
        );

        $res->assertStatus(201)
            ->assertJsonPath('licencePlate', '51G-999.99')
            ->assertJsonPath('make', 'Honda')
            ->assertJsonPath('model', 'City')
            ->assertJsonPath('year', 2022)
            ->assertJsonPath('vin', null);

        $this->assertDatabaseHas('vehicles', [
            'customer_id' => $customer->id,
            'licence_plate' => '51G-999.99',
            'plate_key' => '51G99999',
        ]);

        // 2. Customer adds same plate with different spacing/casing '51g 99999' -> 409 PLATE_ALREADY_REGISTERED
        $resDuplicate = $this->actingAs($user)->postJson(
            '/api/v1/me/vehicles',
            [
                'licencePlate' => '51g 99999',
                'make' => 'Honda',
                'model' => 'City',
                'year' => 2022,
                'vin' => null,
            ],
            ['Idempotency-Key' => (string) Str::uuid()]
        );

        $resDuplicate->assertStatus(409)
            ->assertJsonPath('code', 'PLATE_ALREADY_REGISTERED');
    }

    public function test_vehicle_limit_and_vin_unique(): void
    {
        [$user, $customer] = $this->createCustomerUser('Customer With Vehicles', 'vehicles@example.test', '0903000000');

        // Given: Customer already has 10 vehicles
        for ($i = 1; $i <= 10; $i++) {
            $num = str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            Vehicle::create([
                'customer_id' => $customer->id,
                'licence_plate' => "51A-000.{$num}",
                'plate_key' => "51A000{$num}",
                'vin' => "RL4BT3F3X0M0012{$num}",
                'make' => 'Toyota',
                'model' => 'Vios',
                'year' => 2020,
            ]);
        }

        // Another vehicle exists with VIN V
        [$otherUser, $otherCustomer] = $this->createCustomerUser('Other Customer', 'other@example.test', '0903999999');
        $vinV = '1HGBH41JXMN109186';
        Vehicle::create([
            'customer_id' => $otherCustomer->id,
            'licence_plate' => '51B-111.11',
            'plate_key' => '51B11111',
            'vin' => $vinV,
            'make' => 'Honda',
            'model' => 'Civic',
            'year' => 2021,
        ]);

        // When 1: Customer tries to add 11th vehicle -> 422 VEHICLE_LIMIT_REACHED
        $res11 = $this->actingAs($user)->postJson(
            '/api/v1/me/vehicles',
            [
                'licencePlate' => '51A-999.99',
                'make' => 'Toyota',
                'model' => 'Camry',
                'year' => 2023,
                'vin' => null,
            ],
            ['Idempotency-Key' => (string) Str::uuid()]
        );

        $res11->assertStatus(422)
            ->assertJsonPath('code', 'VEHICLE_LIMIT_REACHED');

        // When 2: Customer with < 10 vehicles tries to add vehicle with VIN V in lowercase -> 409 VIN_ALREADY_REGISTERED
        [$freshUser] = $this->createCustomerUser('Fresh Customer', 'fresh@example.test', '0903111222');
        $resVin = $this->actingAs($freshUser)->postJson(
            '/api/v1/me/vehicles',
            [
                'licencePlate' => '51K-555.55',
                'make' => 'Honda',
                'model' => 'CR-V',
                'year' => 2022,
                'vin' => strtolower($vinV),
            ],
            ['Idempotency-Key' => (string) Str::uuid()]
        );

        $resVin->assertStatus(409)
            ->assertJsonPath('code', 'VIN_ALREADY_REGISTERED');
    }

    public function test_get_my_vehicles_returns_list(): void
    {
        [$user, $customer] = $this->createCustomerUser('List Customer', 'list@example.test', '0903888777');
        Vehicle::create([
            'customer_id' => $customer->id,
            'licence_plate' => '51A-123.45',
            'plate_key' => '51A12345',
            'vin' => 'RL4BT3F3X0M001299',
            'make' => 'Toyota',
            'model' => 'Vios',
            'year' => 2021,
        ]);

        $res = $this->actingAs($user)->getJson('/api/v1/me/vehicles');
        $res->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.licencePlate', '51A-123.45')
            ->assertJsonPath('data.0.make', 'Toyota')
            ->assertJsonPath('data.0.model', 'Vios');
    }

    public function test_my_vehicles_authorisation_and_validation_rules(): void
    {
        // 1. Unauthenticated -> 401 UNAUTHENTICATED
        $this->getJson('/api/v1/me/vehicles')
            ->assertStatus(401)
            ->assertJsonPath('code', 'UNAUTHENTICATED');

        $world = World::saigonD7();
        $advisor = $world->advisor();
        [$customerUser] = $this->createCustomerUser('Auth User', 'auth@example.test', '0903777666');

        // 2. Advisor cannot access GET /me/vehicles -> 403 FORBIDDEN
        $this->actingAs($advisor)->getJson('/api/v1/me/vehicles')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        // 3. Advisor cannot access POST /me/vehicles -> 403 FORBIDDEN
        $this->actingAs($advisor)->postJson('/api/v1/me/vehicles', [
            'licencePlate' => '51A-123.45',
            'make' => 'Toyota',
            'model' => 'Vios',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');

        // 4. Missing Idempotency-Key on POST -> 400 IDEMPOTENCY_KEY_REQUIRED
        $this->actingAs($customerUser)->postJson('/api/v1/me/vehicles', [
            'licencePlate' => '51A-123.45',
            'make' => 'Toyota',
            'model' => 'Vios',
        ])->assertStatus(400)
            ->assertJsonPath('code', 'IDEMPOTENCY_KEY_REQUIRED');

        // 5. Invalid plate "!!!" -> 400 VALIDATION_ERROR
        $this->actingAs($customerUser)->postJson('/api/v1/me/vehicles', [
            'licencePlate' => '!!!',
            'make' => 'Toyota',
            'model' => 'Vios',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // 6. Invalid VIN (16 chars) -> 400 VALIDATION_ERROR
        $this->actingAs($customerUser)->postJson('/api/v1/me/vehicles', [
            'licencePlate' => '51A-123.45',
            'make' => 'Toyota',
            'model' => 'Vios',
            'vin' => '1234567890123456',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');

        // 7. Invalid VIN (contains letter O) -> 400 VALIDATION_ERROR
        $this->actingAs($customerUser)->postJson('/api/v1/me/vehicles', [
            'licencePlate' => '51A-123.45',
            'make' => 'Toyota',
            'model' => 'Vios',
            'vin' => '1234567890123456O',
        ], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(400)
            ->assertJsonPath('code', 'VALIDATION_ERROR');
    }
}
