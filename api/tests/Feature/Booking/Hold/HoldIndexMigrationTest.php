<?php

namespace Tests\Feature\Booking\Hold;

use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\World;
use Tests\TestCase;

class HoldIndexMigrationTest extends TestCase
{
    use DatabaseTruncation;

    public function test_DB_10_1_migration_precheck_and_index_creation(): void
    {
        $migration = require database_path('migrations/2026_11_10_000001_add_one_hold_per_customer_index.php');

        // Roll back the index to test pre-check and up()
        $migration->down();
        $this->assertFalse($this->hasOneHoldPerCustomerIndex());

        try {
            $world = World::saigonD7();
            ['user' => $customerUser, 'customer' => $customer, 'vehicle' => $vehicle] = $world->customerWithVehicle();
            $service = $world->serviceType('OIL_CHANGE');
            $technician = $world->technician('An');
            $bay = $world->bay('K1');

            $now = CarbonImmutable::parse('2026-11-02 08:00:00', 'Asia/Ho_Chi_Minh');

            $appt1Id = (string) Str::uuid();
            $appt2Id = (string) Str::uuid();

            // Insert two HELD appointments for the same customer (permitted while index is absent)
            DB::table('appointments')->insert([
                'id' => $appt1Id,
                'reference' => 'APT-20261102-0001',
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'service_type_id' => $service->id,
                'technician_id' => $technician->id,
                'bay_id' => $bay->id,
                'status' => 'HELD',
                'start_at' => '2026-11-02 10:00:00+07',
                'end_at' => '2026-11-02 10:30:00+07',
                'expires_at' => '2026-11-02 08:05:00+07',
                'version' => 1,
                'source' => 'CUSTOMER',
                'created_by_user_id' => $customerUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('appointments')->insert([
                'id' => $appt2Id,
                'reference' => 'APT-20261102-0002',
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'service_type_id' => $service->id,
                'technician_id' => $technician->id,
                'bay_id' => $bay->id,
                'status' => 'HELD',
                'start_at' => '2026-11-02 11:00:00+07',
                'end_at' => '2026-11-02 11:30:00+07',
                'expires_at' => '2026-11-02 08:05:00+07',
                'version' => 1,
                'source' => 'CUSTOMER',
                'created_by_user_id' => $customerUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // When running the migration with conflicting holds: it stops with the message
            try {
                $migration->up();
                $this->fail('Expected migration to throw RuntimeException due to conflicting HELD appointments');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString($customer->id, $e->getMessage());
                $this->assertStringContainsString('Expire holds first', $e->getMessage());
            }

            $this->assertFalse($this->hasOneHoldPerCustomerIndex());

            // After expiring one hold:
            DB::table('appointments')->where('id', $appt1Id)->update(['status' => 'EXPIRED', 'version' => 2]);

            // Now run migration: it succeeds
            $migration->up();
            $this->assertTrue($this->hasOneHoldPerCustomerIndex());

            // Verify unique constraint: attempting to insert another HELD appointment for same customer fails
            $appt3Id = (string) Str::uuid();
            try {
                DB::table('appointments')->insert([
                    'id' => $appt3Id,
                    'reference' => 'APT-20261102-0003',
                    'dealership_id' => $world->dealership->id,
                    'customer_id' => $customer->id,
                    'service_type_id' => $service->id,
                    'technician_id' => $technician->id,
                    'bay_id' => $bay->id,
                    'status' => 'HELD',
                    'start_at' => '2026-11-02 14:00:00+07',
                    'end_at' => '2026-11-02 14:30:00+07',
                    'expires_at' => '2026-11-02 08:05:00+07',
                    'version' => 1,
                    'source' => 'CUSTOMER',
                    'created_by_user_id' => $customerUser->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $this->fail('Expected unique constraint violation on appointments_one_hold_per_customer');
            } catch (QueryException $e) {
                $this->assertSame('23505', $e->getCode());
            }

            // But inserting a non-HELD appointment (e.g. CONFIRMED) for the same customer succeeds
            DB::table('appointments')->insert([
                'id' => $appt3Id,
                'reference' => 'APT-20261102-0003',
                'dealership_id' => $world->dealership->id,
                'customer_id' => $customer->id,
                'vehicle_id' => $vehicle->id,
                'service_type_id' => $service->id,
                'technician_id' => $technician->id,
                'bay_id' => $bay->id,
                'status' => 'CONFIRMED',
                'start_at' => '2026-11-02 14:00:00+07',
                'end_at' => '2026-11-02 14:30:00+07',
                'expires_at' => null,
                'version' => 1,
                'source' => 'CUSTOMER',
                'created_by_user_id' => $customerUser->id,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            // Test down() drops the index cleanly
            $migration->down();
            $this->assertFalse($this->hasOneHoldPerCustomerIndex());

            // Re-run up() to restore schema for subsequent tests
            $migration->up();
            $this->assertTrue($this->hasOneHoldPerCustomerIndex());
        } finally {
            if (! $this->hasOneHoldPerCustomerIndex()) {
                $migration->up();
            }
        }
    }

    private function hasOneHoldPerCustomerIndex(): bool
    {
        /** @var list<object{indexname: string}> $rows */
        $rows = DB::select(
            "SELECT indexname FROM pg_indexes WHERE tablename = 'appointments' AND indexname = 'appointments_one_hold_per_customer'"
        );

        return count($rows) > 0;
    }
}
