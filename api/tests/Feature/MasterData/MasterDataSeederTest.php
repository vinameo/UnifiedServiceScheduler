<?php

namespace Tests\Feature\MasterData;

use App\Domain\MasterData\BayType;
use App\Models\Bay;
use App\Models\Customer;
use App\Models\Dealership;
use App\Models\DealershipOpeningHour;
use App\Models\ServiceType;
use App\Models\Technician;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\Artisan;
use Tests\Support\World;
use Tests\TestCase;

class MasterDataSeederTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * @var array<string, mixed>
     */
    private array $originalConfig = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['admin_password', 'advisor_password', 'foreman_password', 'customer_password'] as $key) {
            $this->originalConfig[$key] = config("auth.seed.{$key}");
            config(["auth.seed.{$key}" => "seed_password_{$key}"]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->originalConfig as $key => $value) {
            config(["auth.seed.{$key}" => $value]);
        }

        parent::tearDown();
    }

    /**
     * TC-01-09 (INV-01.6; Integration):
     * Given Empty DB; when db:seed twice; record ids after run 1;
     * then Row counts unchanged; ids unchanged; a technician disabled between the runs stays disabled.
     */
    public function test_INV_01_6_seeding_twice_changes_nothing(): void
    {
        $exitCode = Artisan::call('db:seed');
        $this->assertSame(0, $exitCode);

        // Check initial row counts
        $this->assertSame(1, Dealership::count());
        $this->assertSame(6, DealershipOpeningHour::count());
        $this->assertSame(3, ServiceType::count());
        $this->assertSame(4, Bay::count());
        $this->assertSame(5, Technician::count());
        $this->assertSame(3, Vehicle::count());
        $this->assertSame(5, User::count());
        $this->assertSame(2, Customer::count());

        // Huỳnh Tấn Phát is seeded disabled (active = false)
        $phat = Technician::where('name', 'Huỳnh Tấn Phát')->firstOrFail();
        $this->assertFalse($phat->active);
        $this->assertSame(1, $phat->level);

        // Record all IDs after run 1
        $dealershipIds = Dealership::orderBy('code')->pluck('id', 'code')->all();
        $openingHourIds = DealershipOpeningHour::orderBy('weekday')->pluck('id', 'weekday')->all();
        $serviceTypeIds = ServiceType::orderBy('code')->pluck('id', 'code')->all();
        $bayIds = Bay::orderBy('code')->pluck('id', 'code')->all();
        $technicianIds = Technician::orderBy('name')->pluck('id', 'name')->all();
        $vehicleIds = Vehicle::orderBy('plate_key')->pluck('id', 'plate_key')->all();
        $userIds = User::orderBy('email')->pluck('id', 'email')->all();
        $customerIds = Customer::orderBy('user_id')->pluck('id', 'user_id')->all();

        // An admin disables Võ Thị Chi between runs
        $chi = Technician::where('name', 'Võ Thị Chi')->firstOrFail();
        $this->assertTrue($chi->active);
        $this->assertSame(4, $chi->level);
        $chi->update(['active' => false]);
        $this->assertFalse($chi->refresh()->active);

        // Second run
        $exitCode2 = Artisan::call('db:seed');
        $this->assertSame(0, $exitCode2);

        // Row counts remain unchanged
        $this->assertSame(1, Dealership::count());
        $this->assertSame(6, DealershipOpeningHour::count());
        $this->assertSame(3, ServiceType::count());
        $this->assertSame(4, Bay::count());
        $this->assertSame(5, Technician::count());
        $this->assertSame(3, Vehicle::count());
        $this->assertSame(5, User::count());
        $this->assertSame(2, Customer::count());

        // IDs remain identical
        $this->assertSame($dealershipIds, Dealership::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame($openingHourIds, DealershipOpeningHour::orderBy('weekday')->pluck('id', 'weekday')->all());
        $this->assertSame($serviceTypeIds, ServiceType::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame($bayIds, Bay::orderBy('code')->pluck('id', 'code')->all());
        $this->assertSame($technicianIds, Technician::orderBy('name')->pluck('id', 'name')->all());
        $this->assertSame($vehicleIds, Vehicle::orderBy('plate_key')->pluck('id', 'plate_key')->all());
        $this->assertSame($userIds, User::orderBy('email')->pluck('id', 'email')->all());
        $this->assertSame($customerIds, Customer::orderBy('user_id')->pluck('id', 'user_id')->all());

        // Critical: Chi stays disabled after the second seed run
        $chiAfterSecondRun = Technician::where('name', 'Võ Thị Chi')->firstOrFail();
        $this->assertFalse($chiAfterSecondRun->active);
        $this->assertSame(4, $chiAfterSecondRun->level);

        // Huỳnh Tấn Phát stays disabled
        $phatAfterSecondRun = Technician::where('name', 'Huỳnh Tấn Phát')->firstOrFail();
        $this->assertFalse($phatAfterSecondRun->active);

        // Active technicians remain active
        $an = Technician::where('name', 'Trần Minh An')->firstOrFail();
        $this->assertTrue($an->active);
        $dung = Technician::where('name', 'Lý Hoàng Dũng')->firstOrFail();
        $this->assertTrue($dung->active);
        $em = Technician::where('name', 'Ngô Bảo Em')->firstOrFail();
        $this->assertTrue($em->active);
    }

    public function test_world_fixture_builds_dealership_and_helpers(): void
    {
        $world = World::saigonD7();

        $this->assertSame('SGA-D7', $world->dealership->code);
        $this->assertSame('Saigon Auto', $world->dealership->name);

        // Accessors
        $service = $world->serviceType('OIL_CHANGE');
        $this->assertSame('OIL_CHANGE', $service->code);
        $this->assertSame(60, $service->duration_minutes);
        $this->assertSame(1, $service->required_level);
        $this->assertSame(BayType::QUICK, $service->required_bay_type);

        $techFull = $world->technician('Trần Minh An');
        $this->assertSame(2, $techFull->level);
        $this->assertTrue($techFull->active);

        $techSub = $world->technician('An');
        $this->assertSame($techFull->id, $techSub->id);

        $bay = $world->bay('K2');
        $this->assertSame('K2', $bay->code);
        $this->assertSame(BayType::QUICK, $bay->bay_type);

        // onlyTechnicians manipulator
        $world->onlyTechnicians(['Trần Minh An']);
        $this->assertTrue($world->technician('Trần Minh An')->active);
        $this->assertFalse($world->technician('Lý Hoàng Dũng')->active);
        $this->assertFalse($world->technician('Võ Thị Chi')->active);
        $this->assertFalse($world->technician('Ngô Bảo Em')->active);

        // onlyBays manipulator
        $world->onlyBays(['K1', 'K3']);
        $this->assertTrue($world->bay('K1')->active);
        $this->assertFalse($world->bay('K2')->active);
        $this->assertTrue($world->bay('K3')->active);
        $this->assertFalse($world->bay('KEV')->active);

        // customerWithVehicle helper
        [$user, $customer, $vehicle] = $world->customerWithVehicle();
        $this->assertInstanceOf(User::class, $user);
        $this->assertInstanceOf(Customer::class, $customer);
        $this->assertInstanceOf(Vehicle::class, $vehicle);
        $this->assertSame($customer->id, $vehicle->customer_id);
        $this->assertSame($user->id, $customer->user_id);
    }

    public function test_vehicle_seeder_skips_when_customer_is_missing(): void
    {
        config(['auth.seed.customer_password' => '']);

        $exitCode = Artisan::call('db:seed');
        $this->assertSame(0, $exitCode);

        // Dealership and staff seeded, but no customers and no vehicles
        $this->assertSame(1, Dealership::count());
        $this->assertSame(5, Technician::count());
        $this->assertSame(0, Customer::count());
        $this->assertSame(0, Vehicle::count());
    }
}
