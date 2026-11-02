<?php

namespace Tests\Unit\Auth;

use App\Domain\Auth\Role;
use PHPUnit\Framework\TestCase;

class RoleTest extends TestCase
{
    public function test_role_cases_and_values(): void
    {
        $this->assertSame('CUSTOMER', Role::CUSTOMER->value);
        $this->assertSame('SERVICE_ADVISOR', Role::SERVICE_ADVISOR->value);
        $this->assertSame('WORKSHOP_FOREMAN', Role::WORKSHOP_FOREMAN->value);
        $this->assertSame('ADMIN', Role::ADMIN->value);
    }

    public function test_is_staff(): void
    {
        $this->assertFalse(Role::CUSTOMER->isStaff());
        $this->assertTrue(Role::SERVICE_ADVISOR->isStaff());
        $this->assertTrue(Role::WORKSHOP_FOREMAN->isStaff());
        $this->assertTrue(Role::ADMIN->isStaff());
    }

    public function test_label(): void
    {
        $this->assertSame('Customer', Role::CUSTOMER->label());
        $this->assertSame('Service advisor', Role::SERVICE_ADVISOR->label());
        $this->assertSame('Workshop foreman', Role::WORKSHOP_FOREMAN->label());
        $this->assertSame('Admin', Role::ADMIN->label());
    }
}
