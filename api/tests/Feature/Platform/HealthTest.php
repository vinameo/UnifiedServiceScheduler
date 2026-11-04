<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_FR_08_5_live_answers_without_a_session(): void
    {
        $this->getJson('/health/live')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_FR_08_5_ready_when_the_database_answers(): void
    {
        $this->getJson('/health/ready')
            ->assertOk()
            ->assertExactJson(['status' => 'ok', 'checks' => ['database' => 'ok']]);
    }

    public function test_AC_08_3_ready_is_503_when_database_is_down(): void
    {
        $defaultConnection = config('database.default');
        $originalHost = config("database.connections.{$defaultConnection}.host");

        try {
            config(["database.connections.{$defaultConnection}.host" => 'unreachable.invalid']);
            DB::purge($defaultConnection);

            $this->getJson('/health/ready')
                ->assertStatus(503)
                ->assertExactJson([
                    'status' => 'fail',
                    'checks' => [
                        'database' => 'fail',
                    ],
                ]);
        } finally {
            config(["database.connections.{$defaultConnection}.host" => $originalHost]);
            DB::purge($defaultConnection);
            DB::reconnect($defaultConnection);
        }
    }
}
