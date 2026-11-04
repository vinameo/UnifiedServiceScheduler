<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records request start time, then emits exactly one 'request' log line on termination (D-08.3).
 *
 * In ticket 03, this terminable middleware will also increment Prometheus counters in metric_counters.
 */
final class RecordRequestOutcome
{
    private const START_ATTRIBUTE = '_request_start_hrtime';

    public function handle(Request $request, Closure $next): Response
    {
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
        } catch (Throwable $e) {
            Log::warning('Failed to record request outcome: '.$e->getMessage());
        }
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
