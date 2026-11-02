<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use Tests\TestCase;

class NoSecretsTest extends TestCase
{
    use RefreshDatabase;

    public function test_INV_00_2_no_password_or_email_in_responses_or_logs(): void
    {
        $testHandler = new TestHandler;
        /** @var Logger $monolog */
        $monolog = Log::getLogger();
        $monolog->pushHandler($testHandler);

        $email = 'nosecrets@mail.com';
        $password = 'secretPassword123';

        // 1. Sign up
        $signUpResponse = $this->postJson('/api/v1/auth/customer/sign-up', [
            'email' => $email,
            'password' => $password,
            'passwordConfirmation' => $password,
            'fullName' => 'No Secrets Customer',
            'phone' => '0903123218',
        ]);
        $signUpResponse->assertStatus(201);
        $signUpResponse->assertDontSee($password);
        $this->assertArrayNotHasKey('password', $signUpResponse->json());

        // 2. Failed sign-in
        $signInResponse = $this->postJson('/api/v1/auth/staff/sign-in', [
            'email' => $email,
            'password' => $password,
        ]);
        $signInResponse->assertStatus(401);
        $signInResponse->assertDontSee($password);
        $this->assertArrayNotHasKey('password', $signInResponse->json());

        // 3. GET /me (using session from sign-up)
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(200);
        $meResponse->assertDontSee($password);
        $this->assertArrayNotHasKey('password', $meResponse->json());

        // Verify logs: no log entry should contain the email or password
        $records = $testHandler->getRecords();
        $this->assertNotEmpty($records);

        foreach ($records as $record) {
            $serialized = json_encode($record->toArray()) ?: $record->message;
            $this->assertStringNotContainsString($email, $serialized);
            $this->assertStringNotContainsString($password, $serialized);
        }
    }
}
