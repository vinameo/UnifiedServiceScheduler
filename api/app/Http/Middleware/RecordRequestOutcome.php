<?php

namespace App\Http\Middleware;

use App\Domain\Monitoring\Services\MetricCounterServiceInterface;
use App\Domain\Monitoring\Services\RequestMetrics;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records request start time, then emits exactly one 'request' log line on termination (D-08.3),
 * and increments Prometheus counters in metric_counters (D-08.5, D-08.6).
 */
final class RecordRequestOutcome
{
    private const START_ATTRIBUTE = '_request_start_hrtime';

    public function __construct(
        private readonly MetricCounterServiceInterface $metricService,
        private readonly RequestMetrics $requestMetrics,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->requestMetrics->reset();
        $request->attributes->set(self::START_ATTRIBUTE, hrtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $start = $request->attributes->get(self::START_ATTRIBUTE);
            $durationMs = $start !== null
                ? round((hrtime(true) - (int) $start) / 1e6, 2)
                : 0.0;

            $status = $response->getStatusCode();
            $route = $this->resolveRoute($request);
            $code = $this->resolveErrorCode($response);
            $outcome = $this->resolveOutcome($status, $code);

            $request->attributes->set('outcome', $outcome);

            Log::info('request', [
                'method' => $request->getMethod(),
                'route' => $route,
                'status' => $status,
                'code' => $code,
                'outcome' => $outcome,
                'durationMs' => $durationMs,
            ]);

            $this->recordMetrics($request, $response, $outcome, $code, $durationMs);
        } catch (Throwable $e) {
            Log::warning('Failed to record request outcome: '.$e->getMessage());
        }
    }

    private function recordMetrics(
        Request $request,
        Response $response,
        string $outcome,
        ?string $code,
        float $durationMs
    ): void {
        $isBooking = $this->isBookingRoute($request);
        $isReplay = $this->isIdempotentReplay($response);

        if ($isBooking) {
            if ($isReplay) {
                $this->metricService->recordReplay();
            } else {
                $this->metricService->recordOutcome($outcome);
                $this->metricService->recordDuration($durationMs / 1000.0);
            }
        } elseif ($isReplay) {
            $this->metricService->recordReplay();
        }

        $retries = $this->requestMetrics->getAllocationRetriesCount();
        if ($retries > 0) {
            $this->metricService->recordAllocationRetries($retries);
        }

        if ($code === 'APPOINTMENT_MODIFIED' || $this->requestMetrics->hasAppointmentModified()) {
            $this->metricService->recordAppointmentModified();
        }

        if ($this->isSignInRoute($request) && in_array($code, ['INVALID_CREDENTIALS', 'TOO_MANY_ATTEMPTS'], true)) {
            $this->metricService->recordSignInFailure();
        }
    }

    private function isBookingRoute(Request $request): bool
    {
        if ($request->isMethod('POST') && (
            $request->is('api/v1/appointments') ||
            $request->is('appointments') ||
            $request->is('api/v1/_testing/appointments') ||
            $request->is('_testing/appointments')
        )) {
            return true;
        }

        if (app()->environment('testing') && $request->is('*/_testing/fail')) {
            return true;
        }

        return false;
    }

    private function isIdempotentReplay(Response $response): bool
    {
        return $response->headers->get('Idempotent-Replayed') === 'true'
            || $this->requestMetrics->hasReplayed();
    }

    private function isSignInRoute(Request $request): bool
    {
        return $request->isMethod('POST') && (
            $request->is('api/v1/auth/customer/sign-in') ||
            $request->is('api/v1/auth/staff/sign-in') ||
            $request->is('*/auth/*/sign-in') ||
            str_ends_with($request->path(), '/sign-in')
        );
    }

    private function resolveRoute(Request $request): string
    {
        $route = $request->route();
        $uri = $route instanceof Route ? $route->uri() : $request->path();

        return $request->getMethod().' '.$uri;
    }

    private function resolveErrorCode(Response $response): ?string
    {
        $contentType = (string) $response->headers->get('Content-Type');
        if (str_contains($contentType, 'json')) {
            $content = $response->getContent();
            if ($content !== false && $content !== '') {
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['code']) && is_string($decoded['code'])) {
                    return $decoded['code'];
                }
            }
        }

        return null;
    }

    private function resolveOutcome(int $status, ?string $code): string
    {
        return match (true) {
            $status === 201 => 'confirmed',
            $status >= 200 && $status < 300 => 'success',
            $status === 409 && $code === 'NO_AVAILABILITY' => 'no_availability',
            $status >= 400 && $status < 500 => 'validation_error',
            default => 'error',
        };
    }
}
