<?php

namespace Tests\Feature\Booking;

use Illuminate\Support\Facades\Process;
use Tests\TestCase;

class TestingRoutesTest extends TestCase
{
    public function test_AC_04_23_testing_routes_only_in_testing(): void
    {
        $artisan = base_path('artisan');

        // Check routes list under APP_ENV=local
        $routesProcess = Process::run("APP_ENV=local php {$artisan} route:list --json");
        $this->assertTrue($routesProcess->successful(), 'route:list command failed: '.$routesProcess->errorOutput());

        $routes = json_decode($routesProcess->output(), true);
        $this->assertIsArray($routes);

        /** @var list<array{uri?: string}> $routes */
        foreach ($routes as $route) {
            $uri = $route['uri'] ?? '';
            $this->assertStringNotContainsString('_testing', $uri, "Expected no _testing route in APP_ENV=local, found: {$uri}");
        }

        // Check commands list under APP_ENV=local
        $commandsProcess = Process::run("APP_ENV=local php {$artisan} list --raw");
        $this->assertTrue($commandsProcess->successful(), 'list command failed: '.$commandsProcess->errorOutput());
        $this->assertStringNotContainsString('testing:request', $commandsProcess->output(), 'Expected testing:request command to be absent in APP_ENV=local');
    }
}
