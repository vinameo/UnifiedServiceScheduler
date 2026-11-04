<?php

namespace Tests\Feature\Monitoring;

use App\Domain\Monitoring\Repositories\MetricCounterRepositoryInterface;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    use DatabaseTruncation;

    /**
     * TC-08-07 (AC-08.7, INV-08.5; Integration):
     * given —
     * when GET /metrics without the flag; with X-Forwarded-For; with server var METRICS_INTERNAL=1;
     * then 404; 404; 200 text/plain with # TYPE booking_requests_total counter.
     */
    public function test_AC_08_7_metrics_only_from_internal_nginx(): void
    {
        // 1. Direct request without the internal flag -> 404
        $resWithoutFlag = $this->get('/metrics');
        $resWithoutFlag->assertStatus(404);

        // 2. Request with X-Forwarded-For (header must NOT be trusted) -> 404
        $resWithForwarded = $this->withHeaders([
            'X-Forwarded-For' => '127.0.0.1',
        ])->get('/metrics');
        $resWithForwarded->assertStatus(404);

        $resWithPrivateForwarded = $this->withHeaders([
            'X-Forwarded-For' => '10.0.0.1',
        ])->get('/metrics');
        $resWithPrivateForwarded->assertStatus(404);

        // Header spoofing attempts -> 404
        $resWithHeader = $this->withHeaders([
            'METRICS_INTERNAL' => '1',
            'X-Metrics-Internal' => '1',
        ])->get('/metrics');
        $resWithHeader->assertStatus(404);

        // Non-1 server variables -> 404
        $resWithZero = $this->withServerVariables([
            'METRICS_INTERNAL' => '0',
        ])->get('/metrics');
        $resWithZero->assertStatus(404);

        $resWithTrue = $this->withServerVariables([
            'METRICS_INTERNAL' => 'true',
        ])->get('/metrics');
        $resWithTrue->assertStatus(404);

        // 3. Request through internal Nginx with server var METRICS_INTERNAL=1 -> 200 text/plain
        $repo = app(MetricCounterRepositoryInterface::class);
        $repo->increment('booking_requests_total', 'outcome="confirmed"', 1);

        $resInternal = $this->withServerVariables([
            'METRICS_INTERNAL' => '1',
        ])->get('/metrics');

        $resInternal->assertStatus(200);
        $resInternal->assertHeader('Content-Type', 'text/plain; version=0.0.4; charset=UTF-8');

        $content = $resInternal->getContent();
        $this->assertIsString($content);
        $this->assertStringContainsString('# TYPE booking_requests_total counter', $content);
        $this->assertStringContainsString('booking_requests_total{outcome="confirmed"} 1', $content);
    }
}
