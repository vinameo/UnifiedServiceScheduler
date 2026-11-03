<?php

namespace Tests\Feature\MasterData;

use App\Models\Dealership;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataSchemaTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('badRowsProvider')]
    public function test_INV_01_1_and_01_2_checks_reject_bad_rows(Closure $insert, string $expectedSqlState): void
    {
        $dealership = Dealership::factory()->create();

        DB::statement('SAVEPOINT check_test');
        try {
            $insert($dealership->id);
            DB::statement('RELEASE SAVEPOINT check_test');
            $this->fail("Expected query to fail with SQLSTATE {$expectedSqlState}, but it succeeded.");
        } catch (QueryException $e) {
            DB::statement('ROLLBACK TO SAVEPOINT check_test');
            $this->assertSame($expectedSqlState, $e->getCode(), "Expected SQLSTATE {$expectedSqlState}, got {$e->getCode()}: {$e->getMessage()}");
        }
    }

    /**
     * @return array<string, array{0: Closure(string): void, 1: string}>
     */
    public static function badRowsProvider(): array
    {
        return [
            'service: duration is 0 minutes' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'ZERO_DUR',
                        'name' => 'Zero Duration',
                        'duration_minutes' => 0,
                        'required_level' => 1,
                        'required_bay_type' => 'QUICK',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'service: duration is 50 minutes (not % 15)' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'FIFTY_MIN',
                        'name' => 'Fifty Minutes',
                        'duration_minutes' => 50,
                        'required_level' => 1,
                        'required_bay_type' => 'QUICK',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'service: duration > 480 minutes' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'TOO_LONG',
                        'name' => 'Too Long Duration',
                        'duration_minutes' => 495,
                        'required_level' => 1,
                        'required_bay_type' => 'QUICK',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'service: required_level is 0' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'REQ_LVL_0',
                        'name' => 'Required Level Zero',
                        'duration_minutes' => 60,
                        'required_level' => 0,
                        'required_bay_type' => 'QUICK',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'service: required_level is 8' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'REQ_LVL_8',
                        'name' => 'Required Level Eight',
                        'duration_minutes' => 60,
                        'required_level' => 8,
                        'required_bay_type' => 'QUICK',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'service: required_bay_type is invalid' => [
                function (string $dealershipId): void {
                    DB::table('service_types')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'BAD_BAY_TYPE',
                        'name' => 'Bad Bay Type',
                        'duration_minutes' => 60,
                        'required_level' => 1,
                        'required_bay_type' => 'NONEXISTENT',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'technician: level is 0' => [
                function (string $dealershipId): void {
                    DB::table('technicians')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'name' => 'Tech Level Zero',
                        'level' => 0,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'technician: level is 8' => [
                function (string $dealershipId): void {
                    DB::table('technicians')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'name' => 'Tech Level Eight',
                        'level' => 8,
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'bay: bay_type is invalid' => [
                function (string $dealershipId): void {
                    DB::table('bays')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'code' => 'BAD_BAY',
                        'name' => 'Bad Bay',
                        'bay_type' => 'NONEXISTENT',
                        'active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'opening hours: closes_at <= opens_at (17:00 closing 08:00)' => [
                function (string $dealershipId): void {
                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 1,
                        'opens_at' => '17:00:00',
                        'closes_at' => '08:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'opening hours: closes_at == opens_at (08:00 closing 08:00)' => [
                function (string $dealershipId): void {
                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 1,
                        'opens_at' => '08:00:00',
                        'closes_at' => '08:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'opening hours: weekday is 0' => [
                function (string $dealershipId): void {
                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 0,
                        'opens_at' => '08:00:00',
                        'closes_at' => '17:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'opening hours: weekday is 8' => [
                function (string $dealershipId): void {
                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 8,
                        'opens_at' => '08:00:00',
                        'closes_at' => '17:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23514',
            ],
            'opening hours: two rows for one weekday' => [
                function (string $dealershipId): void {
                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 1,
                        'opens_at' => '08:00:00',
                        'closes_at' => '12:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    DB::table('dealership_opening_hours')->insert([
                        'id' => (string) Str::uuid(),
                        'dealership_id' => $dealershipId,
                        'weekday' => 1,
                        'opens_at' => '13:00:00',
                        'closes_at' => '17:00:00',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                },
                '23505',
            ],
        ];
    }
}
