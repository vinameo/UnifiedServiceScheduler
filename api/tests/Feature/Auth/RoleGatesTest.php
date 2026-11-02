<?php

namespace Tests\Feature\Auth;

use App\Domain\Auth\Role;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class RoleGatesTest extends TestCase
{
    public function test_customer_gate(): void
    {
        $customer = new User;
        $customer->role = Role::CUSTOMER;

        $advisor = new User;
        $advisor->role = Role::SERVICE_ADVISOR;

        $this->assertTrue(Gate::forUser($customer)->allows('customer'));
        $this->assertFalse(Gate::forUser($advisor)->allows('customer'));
    }

    public function test_staff_gate(): void
    {
        $customer = new User;
        $customer->role = Role::CUSTOMER;

        $advisor = new User;
        $advisor->role = Role::SERVICE_ADVISOR;

        $foreman = new User;
        $foreman->role = Role::WORKSHOP_FOREMAN;

        $admin = new User;
        $admin->role = Role::ADMIN;

        $this->assertFalse(Gate::forUser($customer)->allows('staff'));
        $this->assertTrue(Gate::forUser($advisor)->allows('staff'));
        $this->assertTrue(Gate::forUser($foreman)->allows('staff'));
        $this->assertTrue(Gate::forUser($admin)->allows('staff'));
    }

    public function test_gates_work_with_raw_string_role(): void
    {
        $user = new User;
        $user->role = 'CUSTOMER';

        $this->assertTrue(Gate::forUser($user)->allows('customer'));
        $this->assertFalse(Gate::forUser($user)->allows('staff'));

        $user->role = 'SERVICE_ADVISOR';
        $this->assertFalse(Gate::forUser($user)->allows('customer'));
        $this->assertTrue(Gate::forUser($user)->allows('staff'));
    }
}
