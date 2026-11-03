<?php

namespace Tests\Feature\Auth;

use App\Models\Dealership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SignInTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RateLimiter::clear('sign_in:'.hash('sha256', 'ratelimit@mail.com'));
        RateLimiter::clear('sign_in:'.hash('sha256', 'traveluser@mail.com'));
        RateLimiter::clear('sign_in:'.hash('sha256', 'clearcounter@mail.com'));
        parent::tearDown();
    }

    public function test_INV_00_4_unknown_email_and_wrong_password_look_the_same(): void
    {
        $dealership = Dealership::factory()->create();
        User::factory()->advisor($dealership)->create([
            'email' => 'advisor.known@saigonauto.test',
            'password' => 'correct_password_123',
        ]);

        Hash::spy();

        // 1. Sign in with unknown email
        $unknownRes = $this->postJson('/api/v1/auth/staff/sign-in', [
            'email' => 'unknown@saigonauto.test',
            'password' => 'wrong_password_123',
        ]);

        $unknownRes->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'INVALID_CREDENTIALS',
                'title' => 'Email or password is incorrect.',
            ]);

        // 2. Sign in with known email and wrong password
        $knownWrongRes = $this->postJson('/api/v1/auth/staff/sign-in', [
            'email' => 'advisor.known@saigonauto.test',
            'password' => 'wrong_password_123',
        ]);

        $knownWrongRes->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'INVALID_CREDENTIALS',
                'title' => 'Email or password is incorrect.',
            ]);

        // Bodies must be identical except correlationId
        $unknownData = $unknownRes->json();
        $knownWrongData = $knownWrongRes->json();

        unset($unknownData['correlationId'], $knownWrongData['correlationId']);
        $this->assertSame($unknownData, $knownWrongData);

        // Verify Hash::check was called for both
        /** @phpstan-ignore staticMethod.notFound */
        Hash::shouldHaveReceived('check')->twice();
    }

    public function test_INV_00_8_sign_in_regenerates_the_session(): void
    {
        $dealership = Dealership::factory()->create();
        User::factory()->advisor($dealership)->create([
            'email' => 'advisor.session@saigonauto.test',
            'password' => 'correct_password_123',
        ]);

        // Start session before sign-in
        $this->get('/sanctum/csrf-cookie');
        $sessionBefore = session()->getId();

        $response = $this->postJson('/api/v1/auth/staff/sign-in', [
            'email' => 'advisor.session@saigonauto.test',
            'password' => 'correct_password_123',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('role', 'SERVICE_ADVISOR')
            ->assertJsonPath('email', 'advisor.session@saigonauto.test');

        $sessionAfter = session()->getId();

        $this->assertNotEmpty($sessionBefore);
        $this->assertNotEmpty($sessionAfter);
        $this->assertNotSame($sessionBefore, $sessionAfter);
    }

    public function test_AC_00_4_wrong_password_is_401(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer@mail.com',
            'password' => 'secret12',
        ]);

        $response = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'customer@mail.com',
            'password' => 'wrong123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'INVALID_CREDENTIALS',
                'title' => 'Email or password is incorrect.',
            ]);
    }

    #[DataProvider('wrongPortalProvider')]
    public function test_AC_00_6_wrong_portal_is_401(string $role, string $endpoint): void
    {
        $dealership = Dealership::factory()->create();

        if ($role === 'SERVICE_ADVISOR') {
            User::factory()->advisor($dealership)->create([
                'email' => 'staff.portal@saigonauto.test',
                'password' => 'password123',
            ]);
            $email = 'staff.portal@saigonauto.test';
        } else {
            User::factory()->customer()->create([
                'email' => 'customer.portal@saigonauto.test',
                'password' => 'password123',
            ]);
            $email = 'customer.portal@saigonauto.test';
        }

        $response = $this->postJson($endpoint, [
            'email' => $email,
            'password' => 'password123',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'INVALID_CREDENTIALS',
                'title' => 'Email or password is incorrect.',
            ]);
    }

    public function test_AC_00_5_sixth_attempt_is_blocked_even_with_right_password(): void
    {
        User::factory()->customer()->create([
            'email' => 'ratelimit@mail.com',
            'password' => 'correctPassword123',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $res = $this->postJson('/api/v1/auth/customer/sign-in', [
                'email' => 'ratelimit@mail.com',
                'password' => 'wrongPassword',
            ]);
            $res->assertStatus(401);
        }

        $res6 = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'ratelimit@mail.com',
            'password' => 'correctPassword123',
        ]);

        $res6->assertStatus(429)
            ->assertJson([
                'status' => 429,
                'code' => 'TOO_MANY_ATTEMPTS',
            ]);

        $retryAfter = (int) $res6->headers->get('Retry-After');
        $this->assertGreaterThan(0, $retryAfter);
        $this->assertLessThanOrEqual(900, $retryAfter);
    }

    public function test_AC_00_10_lock_ends_after_15_minutes(): void
    {
        User::factory()->customer()->create([
            'email' => 'traveluser@mail.com',
            'password' => 'correctPassword123',
        ]);

        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/api/v1/auth/customer/sign-in', [
                'email' => 'traveluser@mail.com',
                'password' => 'wrongPassword',
            ])->assertStatus(401);
        }

        $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'traveluser@mail.com',
            'password' => 'correctPassword123',
        ])->assertStatus(429);

        // Travel 16 minutes
        $this->travel(16)->minutes();

        $res = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'traveluser@mail.com',
            'password' => 'correctPassword123',
        ]);
        $res->assertStatus(200);
    }

    public function test_INV_00_5_success_clears_the_counter(): void
    {
        User::factory()->customer()->create([
            'email' => 'clearcounter@mail.com',
            'password' => 'correctPassword123',
        ]);

        // 4 failures
        for ($i = 1; $i <= 4; $i++) {
            $this->postJson('/api/v1/auth/customer/sign-in', [
                'email' => 'clearcounter@mail.com',
                'password' => 'wrongPassword',
            ])->assertStatus(401);
        }

        // 1 success
        $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'clearcounter@mail.com',
            'password' => 'correctPassword123',
        ])->assertStatus(200);

        // 3 failures
        for ($i = 1; $i <= 3; $i++) {
            $this->postJson('/api/v1/auth/customer/sign-in', [
                'email' => 'clearcounter@mail.com',
                'password' => 'wrongPassword',
            ])->assertStatus(401);
        }

        // 4th failure since reset: 401 not 429
        $lastRes = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'clearcounter@mail.com',
            'password' => 'wrongPassword',
        ]);
        $lastRes->assertStatus(401)
            ->assertJsonPath('code', 'INVALID_CREDENTIALS');
    }

    public function test_ip_limit_blocks_the_31st_request(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $res = $this->postJson('/api/v1/auth/customer/sign-in', [
                'email' => "unique_{$i}@mail.com",
                'password' => 'somePassword123',
            ]);
            $res->assertStatus(401);
        }

        $res31 = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'unique_31@mail.com',
            'password' => 'somePassword123',
        ]);

        $res31->assertStatus(429)
            ->assertJsonPath('code', 'TOO_MANY_ATTEMPTS');
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function wrongPortalProvider(): array
    {
        return [
            'advisor on customer portal' => ['SERVICE_ADVISOR', '/api/v1/auth/customer/sign-in'],
            'customer on staff portal' => ['CUSTOMER', '/api/v1/auth/staff/sign-in'],
        ];
    }
}
