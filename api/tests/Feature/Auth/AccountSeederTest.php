<?php

namespace Tests\Feature\Auth;

use App\Models\Customer;
use App\Models\Dealership;
use App\Models\User;
use Database\Seeders\AccountSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class AccountSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin_password', 'advisor_password', 'foreman_password', 'customer_password'] as $key) {
            $this->originalConfig[$key] = config("auth.seed.{$key}");
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            config(["auth.seed.{$key}" => $value]);
        }

        parent::tearDown();
    }

    private function setSeedPassword(string $key, ?string $value): void
    {
        config(["auth.seed.{$key}" => $value]);
    }

    public function test_AC_00_11_seeder_is_safe_to_run_twice(): void
    {
        $this->setSeedPassword('admin_password', 'admin1234');
        $this->setSeedPassword('advisor_password', 'advisor1234');
        $this->setSeedPassword('foreman_password', 'foreman1234');
        $this->setSeedPassword('customer_password', 'customer1234');

        $exitCode = Artisan::call('db:seed', ['--class' => AccountSeeder::class]);
        $this->assertSame(0, $exitCode);

        $this->assertSame(1, Dealership::count());
        $this->assertSame(5, User::count()); // 3 staff + 2 customers
        $this->assertSame(3, User::whereNotNull('dealership_id')->count());
        $this->assertSame(2, Customer::count());

        $firstAdmin = User::where('email', 'admin@saigonauto.test')->firstOrFail();
        $firstAdminHash = $firstAdmin->password;

        $firstCustomer = User::where('email', 'hung@example.test')->firstOrFail();
        $firstCustomerHash = $firstCustomer->password;

        // Run second time
        $exitCode2 = Artisan::call('db:seed', ['--class' => AccountSeeder::class]);
        $this->assertSame(0, $exitCode2);

        $this->assertSame(1, Dealership::count());
        $this->assertSame(5, User::count());
        $this->assertSame(2, Customer::count());

        $secondAdmin = User::where('email', 'admin@saigonauto.test')->firstOrFail();
        $this->assertSame($firstAdminHash, $secondAdmin->password);

        $secondCustomer = User::where('email', 'hung@example.test')->firstOrFail();
        $this->assertSame($firstCustomerHash, $secondCustomer->password);
    }

    public function test_AC_00_11_empty_password_env_skips_the_account(): void
    {
        $this->setSeedPassword('admin_password', '');
        $this->setSeedPassword('advisor_password', 'advisor1234');
        $this->setSeedPassword('foreman_password', 'foreman1234');
        $this->setSeedPassword('customer_password', 'customer1234');

        $exitCode = Artisan::call('db:seed', ['--class' => AccountSeeder::class]);
        $this->assertSame(0, $exitCode);

        $this->assertNull(User::where('email', 'admin@saigonauto.test')->first());
        $this->assertNotNull(User::where('email', 'minhanh@saigonauto.test')->first());
        $this->assertNotNull(User::where('email', 'quangtin@saigonauto.test')->first());
        $this->assertNotNull(User::where('email', 'hung@example.test')->first());
        $this->assertNotNull(User::where('email', 'khanh@example.test')->first());
    }
}
