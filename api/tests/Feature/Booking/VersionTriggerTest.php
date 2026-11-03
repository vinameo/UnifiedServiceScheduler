<?php

namespace Tests\Feature\Booking;

use App\Domain\Booking\AppointmentStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\World;
use Tests\TestCase;

class VersionTriggerTest extends TestCase
{
    use DatabaseTruncation;

    public function test_AC_04_13_update_without_increment_is_rejected(): void
    {
        $world = World::saigonD7();
        ['user' => $user, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
        $tech = $world->technician('An');
        $bay = $world->bay('K1');
        $service = $world->serviceType('OIL_CHANGE');

        $now = CarbonImmutable::now();
        $id = (string) Str::uuid();

        DB::table('appointments')->insert([
            'id' => $id,
            'reference' => 'APT-20261105-0001',
            'dealership_id' => $world->dealership->id,
            'customer_id' => $customer->id,
            'vehicle_id' => $vehicle->id,
            'service_type_id' => $service->id,
            'technician_id' => $tech->id,
            'bay_id' => $bay->id,
            'status' => AppointmentStatus::CONFIRMED->value,
            'start_at' => $now->addHours(2),
            'end_at' => $now->addHours(3),
            'version' => 1,
            'source' => 'CUSTOMER',
            'reminder_opt_in' => true,
            'created_by_user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Attempt 1: update status without changing version (version remains 1)
        try {
            DB::table('appointments')->where('id', $id)->update([
                'status' => AppointmentStatus::CANCELLED->value,
                'updated_at' => $now,
            ]);
            $this->fail('Expected QueryException P0001 when version is not incremented');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->getCode());
        }

        // Attempt 2: update status with version incremented by 2 (version = version + 2)
        try {
            DB::table('appointments')->where('id', $id)->update([
                'status' => AppointmentStatus::CANCELLED->value,
                'version' => DB::raw('version + 2'),
                'updated_at' => $now,
            ]);
            $this->fail('Expected QueryException P0001 when version is incremented by 2');
        } catch (QueryException $e) {
            $this->assertSame('P0001', $e->getCode());
        }

        // Attempt 3: valid update with version = version + 1 succeeds
        $affected = DB::table('appointments')->where('id', $id)->update([
            'status' => AppointmentStatus::CANCELLED->value,
            'version' => DB::raw('version + 1'),
            'updated_at' => $now,
        ]);
        $this->assertSame(1, $affected);

        $fresh = DB::table('appointments')->where('id', $id)->first();
        $this->assertNotNull($fresh);
        $this->assertSame(2, $fresh->version);
        $this->assertSame(AppointmentStatus::CANCELLED->value, $fresh->status);
    }
}
