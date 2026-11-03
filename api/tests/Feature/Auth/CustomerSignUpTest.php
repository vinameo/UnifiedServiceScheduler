<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\CustomerSearchResult;
use App\Domain\Auth\Repositories\CustomerRepositoryInterface;
use App\Domain\Auth\Repositories\UserRepository;
use App\Domain\Auth\Repositories\UserRepositoryInterface;
use App\Domain\Auth\Role;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerSignUpTest extends TestCase
{
    use RefreshDatabase;

    public function test_AC_00_1_sign_up_creates_account_customer_and_session(): void
    {
        $payload = [
            'email' => 'hung@mail.com',
            'password' => 'secretPassword123',
            'passwordConfirmation' => 'secretPassword123',
            'fullName' => 'Nguyễn Văn Hùng',
            'phone' => '0903 123 218',
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'email' => 'hung@mail.com',
                'name' => 'Nguyễn Văn Hùng',
                'role' => 'CUSTOMER',
                'dealership' => null,
                'customer' => [
                    'fullName' => 'Nguyễn Văn Hùng',
                    'phone' => '0903123218',
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'hung@mail.com',
            'role' => Role::CUSTOMER->value,
            'dealership_id' => null,
        ]);

        $user = User::where('email', 'hung@mail.com')->firstOrFail();

        $this->assertDatabaseHas('customers', [
            'user_id' => $user->id,
            'full_name' => 'Nguyễn Văn Hùng',
            'phone' => '0903123218',
        ]);

        // Same session GET /me returns authenticated user
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(200)
            ->assertJson([
                'id' => $user->id,
                'email' => 'hung@mail.com',
                'role' => 'CUSTOMER',
            ]);
    }

    public function test_AC_00_2_email_is_case_insensitive(): void
    {
        User::factory()->customer()->create([
            'email' => 'hung@mail.com',
        ]);

        $countBefore = User::count();

        $payload = [
            'email' => 'HUNG@mail.com',
            'password' => 'secretPassword123',
            'passwordConfirmation' => 'secretPassword123',
            'fullName' => 'Nguyễn Văn Hùng',
            'phone' => '0903123218',
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 409,
                'code' => 'EMAIL_ALREADY_USED',
            ]);

        $this->assertSame($countBefore, User::count());
    }

    public function test_INV_00_1_unique_violation_maps_to_409(): void
    {
        User::factory()->customer()->create([
            'email' => 'hung@mail.com',
        ]);

        $countBefore = User::count();

        // Fake repository that always reports "email is free"
        $realRepo = new UserRepository;
        $fakeRepo = new class($realRepo) implements UserRepositoryInterface
        {
            public function __construct(private readonly UserRepository $real) {}

            public function findById(int $id): ?User
            {
                return $this->real->findById($id);
            }

            public function findByEmail(string $email): ?User
            {
                return null;
            }

            public function create(string $name, string $email, string $password, Role $role, ?string $dealershipId = null): User
            {
                return $this->real->create($name, $email, $password, $role, $dealershipId);
            }
        };

        $this->app->instance(UserRepositoryInterface::class, $fakeRepo);

        $payload = [
            'email' => 'hung@mail.com',
            'password' => 'secretPassword123',
            'passwordConfirmation' => 'secretPassword123',
            'fullName' => 'Nguyễn Văn Hùng',
            'phone' => '0903123218',
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(409)
            ->assertJson([
                'status' => 409,
                'code' => 'EMAIL_ALREADY_USED',
            ]);

        $this->assertSame($countBefore, User::count());
    }

    public function test_AC_00_3_weak_password_saves_nothing(): void
    {
        $userCountBefore = User::count();
        $customerCountBefore = Customer::count();

        $payload = [
            'email' => 'weakuser@mail.com',
            'password' => 'abc123',
            'passwordConfirmation' => 'abc123',
            'fullName' => 'Weak User',
            'phone' => '0903123218',
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(422)
            ->assertJson([
                'status' => 422,
                'code' => 'WEAK_PASSWORD',
            ]);

        $this->assertSame($userCountBefore, User::count());
        $this->assertSame($customerCountBefore, Customer::count());
    }

    public function test_INV_00_6_role_in_body_is_ignored(): void
    {
        $payload = [
            'email' => 'clever@mail.com',
            'password' => 'secretPassword123',
            'passwordConfirmation' => 'secretPassword123',
            'fullName' => 'Clever Person',
            'phone' => '0903123218',
            'role' => 'ADMIN',
            'dealershipId' => (string) Str::uuid(),
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'role' => 'CUSTOMER',
                'dealership' => null,
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'clever@mail.com',
            'role' => Role::CUSTOMER->value,
            'dealership_id' => null,
        ]);
    }

    public function test_INV_00_3_sign_up_is_all_or_nothing(): void
    {
        $userCountBefore = User::count();
        $customerCountBefore = Customer::count();

        // Fake repository that breaks the Postgres phone CHECK:
        // CHECK (phone ~ '^\+?[0-9]{9,15}$')
        $breakingCustomerRepo = new class implements CustomerRepositoryInterface
        {
            public function create(int $userId, string $fullName, string $phone, ?string $id = null): Customer
            {
                return Customer::create([
                    'id' => $id ?? (string) Str::uuid(),
                    'user_id' => $userId,
                    'full_name' => $fullName,
                    'phone' => 'invalid_phone_breaks_check',
                ]);
            }

            public function find(string $id): ?Customer
            {
                return null;
            }

            public function findByUserId(int $userId): ?Customer
            {
                return null;
            }

            public function search(string $query, int $limit = 20): CustomerSearchResult
            {
                return new CustomerSearchResult([], 0);
            }
        };

        $this->app->instance(CustomerRepositoryInterface::class, $breakingCustomerRepo);

        $payload = [
            'email' => 'rollback@mail.com',
            'password' => 'secretPassword123',
            'passwordConfirmation' => 'secretPassword123',
            'fullName' => 'Rollback Person',
            'phone' => '0903123218',
        ];

        $response = $this->postJson('/api/v1/auth/customer/sign-up', $payload);

        $response->assertStatus(500)
            ->assertJson([
                'status' => 500,
                'code' => 'INTERNAL_ERROR',
            ]);

        $this->assertSame($userCountBefore, User::count());
        $this->assertSame($customerCountBefore, Customer::count());
        $this->assertDatabaseMissing('users', ['email' => 'rollback@mail.com']);
    }
}
