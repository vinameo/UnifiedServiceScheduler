<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SignOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_AC_00_9_old_session_is_rejected_after_sign_out(): void
    {
        User::factory()->customer()->create([
            'email' => 'customer.signout@mail.com',
            'password' => 'secret123',
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/customer/sign-in', [
            'email' => 'customer.signout@mail.com',
            'password' => 'secret123',
        ]);
        $loginResponse->assertStatus(200);

        // Session is authenticated: GET /me returns 200
        $meResponse = $this->getJson('/api/v1/me');
        $meResponse->assertStatus(200)
            ->assertJsonPath('email', 'customer.signout@mail.com');

        // Sign out returns 204 No Content
        $signOutResponse = $this->postJson('/api/v1/auth/sign-out');
        $signOutResponse->assertStatus(204);

        // Old session is invalidated: subsequent GET /me returns 401 UNAUTHENTICATED
        $subsequentMeResponse = $this->getJson('/api/v1/me');
        $subsequentMeResponse->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'UNAUTHENTICATED',
                'title' => 'Please sign in.',
            ]);
    }
}
