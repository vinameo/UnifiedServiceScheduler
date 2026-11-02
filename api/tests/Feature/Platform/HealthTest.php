<?php

namespace Tests\Feature\Platform;

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
}
