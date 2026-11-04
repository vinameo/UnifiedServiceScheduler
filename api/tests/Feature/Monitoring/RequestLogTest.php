<?php

namespace Tests\Feature\Monitoring;

use Tests\Support\Logging\CapturesLogs;
use Tests\TestCase;

class RequestLogTest extends TestCase
{
    use CapturesLogs;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCapturesLogs();
    }

    /**
     * TC-08-01 (AC-08.1, D-08.3; Integration):
     * given Logs go to TestHandler;
     * when GET /api/v1/me (401) without the header;
     * then Header X-Correlation-Id = X; exactly 1 request log line with correlationId X, status 401, durationMs.
     */
    public function test_AC_08_1_response_and_log_share_correlation_id(): void
    {
        $response = $this->getJson('/api/v1/me');
        $response->assertStatus(401);

        $headerCorrelationId = $response->headers->get('X-Correlation-Id');
        $this->assertNotNull($headerCorrelationId);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9._-]{8,128}$/', $headerCorrelationId);

        $records = $this->testLogHandler->getRecords();
        $requestRecords = array_values(array_filter($records, fn ($r) => $r->message === 'request'));

        $this->assertCount(1, $requestRecords, 'Expected exactly one request log line');

        $requestLog = $requestRecords[0];
        $this->assertSame($headerCorrelationId, $requestLog->context['correlationId']);
        $this->assertSame(401, $requestLog->context['status']);
        $this->assertArrayHasKey('durationMs', $requestLog->context);
        $this->assertGreaterThanOrEqual(0, $requestLog->context['durationMs']);
        $this->assertSame('GET', $requestLog->context['method']);
    }

    public function test_AC_08_1_preserves_client_correlation_id(): void
    {
        $clientId = 'client-req-01935512-7712';
        $response = $this->withHeader('X-Correlation-Id', $clientId)->getJson('/api/v1/me');
        $response->assertStatus(401);
        $response->assertHeader('X-Correlation-Id', $clientId);

        $records = $this->testLogHandler->getRecords();
        $requestRecords = array_values(array_filter($records, fn ($r) => $r->message === 'request'));

        $this->assertCount(1, $requestRecords);
        $this->assertSame($clientId, $requestRecords[0]->context['correlationId']);
    }

    public function test_AC_08_1_preserves_nginx_request_id(): void
    {
        $nginxId = 'nginx-request-id-abcdef1234';
        $response = $this->withHeader('X-Request-Id', $nginxId)->getJson('/api/v1/me');
        $response->assertStatus(401);
        $response->assertHeader('X-Correlation-Id', $nginxId);

        $records = $this->testLogHandler->getRecords();
        $requestRecords = array_values(array_filter($records, fn ($r) => $r->message === 'request'));

        $this->assertCount(1, $requestRecords);
        $this->assertSame($nginxId, $requestRecords[0]->context['correlationId']);
    }
}
