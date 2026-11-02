<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RouteListTest extends TestCase
{
    public function test_INV_00_9_no_staff_sign_up_route(): void
    {
        $routes = Route::getRoutes()->getRoutes();

        foreach ($routes as $route) {
            $uri = $route->uri();

            if (str_contains($uri, 'auth/staff/')) {
                $this->assertSame(
                    'api/v1/auth/staff/sign-in',
                    $uri,
                    "Disallowed route found under auth/staff/: {$uri}"
                );
            }
        }
    }
}
