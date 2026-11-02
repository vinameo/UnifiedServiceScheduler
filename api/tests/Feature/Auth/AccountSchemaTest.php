<?php

namespace Tests\Feature\Auth;

use App\Models\Dealership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AccountSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_DB_00_2_checks_reject_invalid_rows(): void
    {
        $dealership = Dealership::factory()->create();

        $assertThrowsCheckViolation = function (callable $callback, string $failureMessage): void {
            DB::statement('SAVEPOINT check_test');
            try {
                $callback();
                DB::statement('RELEASE SAVEPOINT check_test');
                $this->fail($failureMessage);
            } catch (QueryException $e) {
                DB::statement('ROLLBACK TO SAVEPOINT check_test');
                $this->assertSame('23514', $e->getCode());
            }
        };

        // 1. Advisor without dealership (violates users_staff_has_dealership_check)
        $assertThrowsCheckViolation(function () {
            DB::table('users')->insert([
                'name' => 'Advisor No Dealership',
                'email' => 'advisor.nodealership@example.com',
                'password' => 'hashed_password',
                'role' => 'SERVICE_ADVISOR',
                'dealership_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'Expected QueryException for advisor without dealership');

        // 2. Customer with dealership (violates users_staff_has_dealership_check)
        $assertThrowsCheckViolation(function () use ($dealership) {
            DB::table('users')->insert([
                'name' => 'Customer With Dealership',
                'email' => 'customer.withdealership@example.com',
                'password' => 'hashed_password',
                'role' => 'CUSTOMER',
                'dealership_id' => $dealership->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'Expected QueryException for customer with dealership');

        // 3. Uppercase email direct insert (violates users_email_lowercase_check)
        $assertThrowsCheckViolation(function () {
            DB::table('users')->insert([
                'name' => 'Upper Email',
                'email' => 'Upper@Example.com',
                'password' => 'hashed_password',
                'role' => 'CUSTOMER',
                'dealership_id' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'Expected QueryException for uppercase email');

        // 4. Invalid phone format on customers (violates customers_phone_format_check)
        $validUser = DB::table('users')->insertGetId([
            'name' => 'Valid User',
            'email' => 'valid.customer@example.com',
            'password' => 'hashed_password',
            'role' => 'CUSTOMER',
            'dealership_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $assertThrowsCheckViolation(function () use ($validUser) {
            DB::table('customers')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $validUser,
                'full_name' => 'Invalid Phone Customer',
                'phone' => 'abc',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }, 'Expected QueryException for invalid phone format');
    }
}
