<?php

namespace Tests\Feature\Auth;

use App\Models\Dealership;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class GatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['auth:sanctum', 'can:staff'])->get('/api/v1/_test/staff-only', function () {
            return response()->json(['ok' => true]);
        });

        Route::middleware(['auth:sanctum', 'can:customer'])->get('/api/v1/_test/customer-only', function () {
            return response()->json(['ok' => true]);
        });
    }

    public function test_AC_00_7_customer_cannot_use_staff_routes(): void
    {
        $dealership = Dealership::factory()->create();
        $advisor = User::factory()->advisor($dealership)->create();
        $customer = User::factory()->customer()->create();

        // 1. Customer calls staff-only -> 403 FORBIDDEN
        $this->actingAs($customer);
        $resCustomerOnStaff = $this->getJson('/api/v1/_test/staff-only');
        $resCustomerOnStaff->assertStatus(403)
            ->assertJson([
                'status' => 403,
                'code' => 'FORBIDDEN',
                'title' => 'You may not do this.',
            ]);

        // Customer calling customer-only -> 200 OK
        $resCustomerOnCustomer = $this->getJson('/api/v1/_test/customer-only');
        $resCustomerOnCustomer->assertStatus(200)
            ->assertJson(['ok' => true]);

        // 2. Advisor calls customer-only -> 403 FORBIDDEN
        $this->actingAs($advisor);
        $resAdvisorOnCustomer = $this->getJson('/api/v1/_test/customer-only');
        $resAdvisorOnCustomer->assertStatus(403)
            ->assertJson([
                'status' => 403,
                'code' => 'FORBIDDEN',
                'title' => 'You may not do this.',
            ]);

        // Advisor calling staff-only -> 200 OK
        $resAdvisorOnStaff = $this->getJson('/api/v1/_test/staff-only');
        $resAdvisorOnStaff->assertStatus(200)
            ->assertJson(['ok' => true]);

        // 3. Unauthenticated session calls staff-only -> 401 UNAUTHENTICATED
        $this->app['auth']->forgetGuards();

        $resUnauthStaff = $this->getJson('/api/v1/_test/staff-only');
        $resUnauthStaff->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'UNAUTHENTICATED',
                'title' => 'Please sign in.',
            ]);

        // 4. Unauthenticated session calls customer-only -> 401 UNAUTHENTICATED
        $resUnauthCustomer = $this->getJson('/api/v1/_test/customer-only');
        $resUnauthCustomer->assertStatus(401)
            ->assertJson([
                'status' => 401,
                'code' => 'UNAUTHENTICATED',
                'title' => 'Please sign in.',
            ]);
    }
}
