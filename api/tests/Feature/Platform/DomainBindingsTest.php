<?php

namespace Tests\Feature\Platform;

use App\Providers\DomainServiceProvider;
use Tests\TestCase;

class DomainBindingsTest extends TestCase
{
    public function test_every_bound_interface_resolves_to_its_class(): void
    {
        $bindings = (new DomainServiceProvider($this->app))->bindings;

        $this->assertNotEmpty($bindings);

        foreach ($bindings as $interface => $class) {
            $this->assertInstanceOf($interface, $this->app->make($interface));
            $this->assertInstanceOf($class, $this->app->make($interface));
        }
    }
}
