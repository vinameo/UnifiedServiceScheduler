<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class EnforceCsrfMiddleware extends ValidateCsrfToken
{
    protected function runningUnitTests(): bool
    {
        return false;
    }
}

class AuthAdversarialInputTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('adversarialInputProvider')]
    public function test_adversarial_input(
        string $endpoint,
        array $payload,
        int $expectedStatus,
        ?string $expectedCode = null,
        ?string $expectedRole = null
    ): void {
        if ($endpoint === '/api/v1/auth/customer/sign-in' && ($payload['email'] ?? '') === '  Normalised@Example.Test  ') {
            User::factory()->customer()->create([
                'email' => 'normalised@example.test',
                'password' => 'secretPassword123',
            ]);
        }

        $response = $this->postJson($endpoint, $payload);

        $response->assertStatus($expectedStatus);

        if ($expectedCode !== null) {
            $response->assertJsonPath('code', $expectedCode);
        }

        if ($expectedRole !== null) {
            $response->assertJsonPath('role', $expectedRole);
        }
    }

    public function test_sign_up_body_is_not_json_is_400(): void
    {
        $response = $this->call(
            'POST',
            '/api/v1/auth/customer/sign-up',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'not-valid-json'
        );

        $this->assertSame(400, $response->getStatusCode());
    }

    public function test_missing_xsrf_is_419(): void
    {
        config([
            'sanctum.middleware.validate_csrf_token' => EnforceCsrfMiddleware::class,
        ]);

        $response = $this->withHeaders([
            'Referer' => 'http://localhost:8088/sign-in',
        ])->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'any@example.test',
            'password' => 'secretPassword123',
        ]);

        $response->assertStatus(419)
            ->assertJson([
                'status' => 419,
                'code' => 'CSRF_TOKEN_MISMATCH',
            ]);
    }

    /**
     * @return array<string, array{0: string, 1: array<string, mixed>, 2: int, 3?: string|null, 4?: string|null}>
     */
    public static function adversarialInputProvider(): array
    {
        return [
            'sign-up: email is an array' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => ['a@b.c'],
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Array Email',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: email 300 characters' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => str_repeat('a', 290).'@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Long Email',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: password 73 characters' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'longpassword@mail.com',
                    'password' => str_repeat('a', 73),
                    'passwordConfirmation' => str_repeat('a', 73),
                    'fullName' => 'Long Password',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: password = 12345678 (no letter)' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'noletter@mail.com',
                    'password' => '12345678',
                    'passwordConfirmation' => '12345678',
                    'fullName' => 'No Letter',
                    'phone' => '0903123218',
                ],
                422,
                'WEAK_PASSWORD',
            ],
            'sign-up: password = abcdefgh (no digit)' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'nodigit@mail.com',
                    'password' => 'abcdefgh',
                    'passwordConfirmation' => 'abcdefgh',
                    'fullName' => 'No Digit',
                    'phone' => '0903123218',
                ],
                422,
                'WEAK_PASSWORD',
            ],
            'sign-up: phone = abc123' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'badphone@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Bad Phone',
                    'phone' => 'abc123',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: phone > 15 digits' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'longphone@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Long Phone',
                    'phone' => '+84 90 312 3218 999 999',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: fullName = spaces' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'spaceuser@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => '   ',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: body has role ADMIN' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'adminclaim@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Admin Claim',
                    'phone' => '0903123218',
                    'role' => 'ADMIN',
                ],
                201,
                null,
                'CUSTOMER',
            ],
            'sign-in: email = null' => [
                '/api/v1/auth/customer/sign-in',
                [
                    'email' => null,
                    'password' => 'secretPassword123',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-in: password 10 000 characters' => [
                '/api/v1/auth/customer/sign-in',
                [
                    'email' => 'user@mail.com',
                    'password' => str_repeat('a', 10000),
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: email non-ASCII ánh@mail.com' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'ánh@mail.com',
                    'password' => 'secretPassword123',
                    'passwordConfirmation' => 'secretPassword123',
                    'fullName' => 'Non ASCII Email',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-up: password 30 Vietnamese chars (> 72 bytes)' => [
                '/api/v1/auth/customer/sign-up',
                [
                    'email' => 'vietnamesepw@mail.com',
                    'password' => str_repeat('ệộựữ', 7).'a1',
                    'passwordConfirmation' => str_repeat('ệộựữ', 7).'a1',
                    'fullName' => 'Vietnamese Password',
                    'phone' => '0903123218',
                ],
                400,
                'VALIDATION_ERROR',
            ],
            'sign-in: email with surrounding spaces and upper case' => [
                '/api/v1/auth/customer/sign-in',
                [
                    'email' => '  Normalised@Example.Test  ',
                    'password' => 'secretPassword123',
                ],
                200,
                null,
                'CUSTOMER',
            ],
        ];
    }
}
