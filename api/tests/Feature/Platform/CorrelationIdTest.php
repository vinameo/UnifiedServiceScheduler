<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Str;
use Tests\TestCase;

class CorrelationIdTest extends TestCase
{
    public function test_every_response_carries_a_new_correlation_id(): void
    {
        $id = $this->getJson('/health/live')->headers->get('X-Correlation-Id');

        $this->assertTrue(Str::isUuid($id));
    }

    public function test_a_valid_client_correlation_id_is_kept(): void
    {
        $this->getJson('/health/live', ['X-Correlation-Id' => 'client-abc-123'])
            ->assertHeader('X-Correlation-Id', 'client-abc-123');
    }

    public function test_the_nginx_request_id_is_used_when_the_client_sends_none(): void
    {
        $this->getJson('/health/live', ['X-Request-Id' => '0f1e2d3c4b5a69788796a5b4c3d2e1f0'])
            ->assertHeader('X-Correlation-Id', '0f1e2d3c4b5a69788796a5b4c3d2e1f0');
    }

    public function test_an_invalid_client_correlation_id_is_replaced(): void
    {
        $id = $this->getJson('/health/live', ['X-Correlation-Id' => "bad id\n"])
            ->headers->get('X-Correlation-Id');

        $this->assertTrue(Str::isUuid($id));
    }
}
