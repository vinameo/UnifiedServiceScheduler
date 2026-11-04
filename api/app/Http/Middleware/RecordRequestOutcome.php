<?php

namespace App\Http\Middleware;

use App\Domain\Booking\Exceptions\NoAvailability;
use App\Domain\Monitoring\Services\ApiEventServiceInterface;
use App\Domain\Monitoring\Services\MetricCounterServiceInterface;
use App\Domain\Monitoring\Services\RequestMetrics;
use App\Support\Logging\RedactPersonalData;
use App\Support\Tracing\Tracer;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records request start time, then emits exactly one 'request' log line on termination (D-08.3),
 * increments Prometheus counters in metric_counters (D-08.5, D-08.6), and writes API events
 * to api_events for all POST appointments and errors (D-08.4, INV-08.7, INV-08.4).
 */
final class RecordRequestOutcome
{
    private const START_ATTRIBUTE = '_request_start_hrtime';

    private readonly Tracer $tracer;

    public function __construct(
        private readonly MetricCounterServiceInterface $metricService,
        private readonly RequestMetrics $requestMetrics,
        private readonly ApiEventServiceInterface $apiEventService,
        ?Tracer $tracer = null,
    ) {
        $this->tracer = $tracer ?? app(Tracer::class);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->requestMetrics->reset();
        $request->attributes->set(self::START_ATTRIBUTE, hrtime(true));

        return $this->tracer->span('http.request', function () use ($request, $next) {
            return $next($request);
        }, [
            'http.method' => $request->getMethod(),
            'http.url' => $request->fullUrl(),
        ]);
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

            $this->recordApiEvent($request, $response, $route, $status, $code, $outcome, (int) round($durationMs));
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

    private function recordApiEvent(
        Request $request,
        Response $response,
        string $route,
        int $status,
        ?string $code,
        string $outcome,
        int $durationMs,
    ): void {
        if (! $this->shouldRecordApiEvent($request, $status)) {
            return;
        }

        try {
            $isReplay = $this->isIdempotentReplay($response);
            $retries = $this->requestMetrics->getAllocationRetriesCount();
            $correlationId = $this->resolveCorrelationId($request, $response);
            $dealershipId = $this->resolveDealershipId($request, $response);
            $userId = $this->resolveUserId($request);
            $appointmentId = $this->resolveAppointmentId($request, $response);
            $detail = $this->resolveDetail($response, $status, $code);

            $this->apiEventService->record([
                'occurred_at' => CarbonImmutable::now(),
                'correlation_id' => $correlationId,
                'dealership_id' => $dealershipId,
                'user_id' => $userId,
                'appointment_id' => $appointmentId,
                'method' => $request->getMethod(),
                'route' => $route,
                'status' => $status,
                'code' => $code,
                'outcome' => $outcome,
                'duration_ms' => $durationMs,
                'idempotent_replay' => $isReplay,
                'allocation_retries' => $retries,
                'detail' => $detail,
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to record API event: '.$e->getMessage());
        }
    }

    private function shouldRecordApiEvent(Request $request, int $status): bool
    {
        if ($request->isMethod('POST') && $this->isBookingRoute($request)) {
            return true;
        }

        return $status >= 400;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveDetail(Response $response, int $status, ?string $code): array
    {
        if ($status === 409 && $code === 'NO_AVAILABILITY') {
            $exception = property_exists($response, 'exception') ? $response->exception : null;
            if ($exception instanceof NoAvailability) {
                return [
                    'reason' => $exception->reason,
                    'requestedStart' => $exception->requestedStart->toIso8601String(),
                    'requestedEnd' => $exception->requestedEnd->toIso8601String(),
                    'alternativesCount' => count($exception->alternatives),
                ];
            }

            $decoded = $this->decodeJsonResponse($response);
            if (is_array($decoded)) {
                $alternatives = $decoded['alternatives'] ?? ($decoded['extra']['alternatives'] ?? []);

                return [
                    'reason' => $decoded['reason'] ?? ($decoded['extra']['reason'] ?? 'No availability'),
                    'requestedStart' => $decoded['requestedStart'] ?? ($decoded['extra']['requestedStart'] ?? null),
                    'requestedEnd' => $decoded['requestedEnd'] ?? ($decoded['extra']['requestedEnd'] ?? null),
                    'alternativesCount' => is_array($alternatives) ? count($alternatives) : 0,
                ];
            }
        }

        if ($status >= 500) {
            $exception = property_exists($response, 'exception') ? $response->exception : null;
            if ($exception instanceof Throwable) {
                return [
                    'class' => get_class($exception),
                    'message' => mb_substr(RedactPersonalData::redactString($exception->getMessage()), 0, 200),
                ];
            }

            return [
                'message' => 'Internal server error',
            ];
        }

        if ($status >= 400) {
            $decoded = $this->decodeJsonResponse($response);
            if (is_array($decoded)) {
                $detail = [];
                if (isset($decoded['detail']) && is_string($decoded['detail'])) {
                    $detail['detail'] = $decoded['detail'];
                }
                if (isset($decoded['title']) && is_string($decoded['title'])) {
                    $detail['title'] = $decoded['title'];
                }
                if (isset($decoded['errors']) && is_array($decoded['errors'])) {
                    $detail['errors'] = $decoded['errors'];
                }
                if ($detail === [] && isset($decoded['message']) && is_string($decoded['message'])) {
                    $detail['message'] = $decoded['message'];
                }

                return $detail;
            }

            $exception = property_exists($response, 'exception') ? $response->exception : null;
            if ($exception instanceof Throwable) {
                return [
                    'class' => get_class($exception),
                    'message' => mb_substr(RedactPersonalData::redactString($exception->getMessage()), 0, 200),
                ];
            }
        }

        return [];
    }

    private function resolveCorrelationId(Request $request, Response $response): string
    {
        $id = CorrelationId::of($request);
        if ($id !== null && $id !== '') {
            return $id;
        }

        $header = $response->headers->get(CorrelationId::HEADER)
            ?? $request->headers->get(CorrelationId::HEADER)
            ?? $request->headers->get('X-Request-Id');

        if (is_string($header) && $header !== '') {
            return $header;
        }

        try {
            $shared = Log::sharedContext();
            if (isset($shared['correlationId']) && is_string($shared['correlationId']) && $shared['correlationId'] !== '') {
                return $shared['correlationId'];
            }
        } catch (Throwable) {
        }

        return (string) Str::uuid();
    }

    private function resolveDealershipId(Request $request, Response $response): ?string
    {
        if ($request->attributes->has('dealershipId')) {
            $id = (string) $request->attributes->get('dealershipId');
            if (Str::isUuid($id)) {
                return $id;
            }
        }

        $routeDealership = $request->route('dealership');
        if ($routeDealership !== null) {
            $id = is_object($routeDealership) && isset($routeDealership->id)
                ? (string) $routeDealership->id
                : (string) $routeDealership;
            if (Str::isUuid($id)) {
                return $id;
            }
        }

        try {
            $user = $request->user();
            if ($user !== null && isset($user->dealership_id)) {
                $id = (string) $user->dealership_id;
                if (Str::isUuid($id)) {
                    return $id;
                }
            }
        } catch (Throwable) {
        }

        $bodyDealership = $request->input('dealershipId') ?? $request->input('dealership_id');
        if (is_string($bodyDealership) && Str::isUuid($bodyDealership)) {
            return $bodyDealership;
        }

        $decoded = $this->decodeJsonResponse($response);
        if (is_array($decoded)) {
            $candidate = $decoded['dealershipId']
                ?? $decoded['data']['dealershipId']
                ?? $decoded['data']['dealership_id']
                ?? ($decoded['extra']['dealershipId'] ?? null);
            if (is_string($candidate) && Str::isUuid($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function resolveUserId(Request $request): ?int
    {
        try {
            $user = $request->user() ?? (app()->bound('auth') && Auth::check() ? Auth::user() : null);
            if ($user !== null) {
                $id = $user->getAuthIdentifier();

                return is_numeric($id) ? (int) $id : null;
            }
        } catch (Throwable) {
        }

        return null;
    }

    private function resolveAppointmentId(Request $request, Response $response): ?string
    {
        if ($request->attributes->has('appointmentId')) {
            $id = (string) $request->attributes->get('appointmentId');
            if (Str::isUuid($id)) {
                return $id;
            }
        }

        $routeAppt = $request->route('appointment') ?? $request->route('id');
        if ($routeAppt !== null && $this->isAppointmentRoute($request)) {
            $id = is_object($routeAppt) && isset($routeAppt->id)
                ? (string) $routeAppt->id
                : (string) $routeAppt;
            if (Str::isUuid($id)) {
                return $id;
            }
        }

        $location = $response->headers->get('Location');
        if (is_string($location) && preg_match('/\/appointments\/([0-9a-fA-F-]{36})/', $location, $matches)) {
            return $matches[1];
        }

        $decoded = $this->decodeJsonResponse($response);
        if (is_array($decoded)) {
            $candidate = $decoded['data']['id'] ?? ($decoded['id'] ?? null);
            if (is_string($candidate) && Str::isUuid($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function isAppointmentRoute(Request $request): bool
    {
        $uri = $request->route() instanceof Route ? $request->route()->uri() : $request->path();

        return str_contains($uri, 'appointment');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeJsonResponse(Response $response): ?array
    {
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            return is_array($data) ? $data : null;
        }

        $contentType = (string) $response->headers->get('Content-Type');
        if (! str_contains($contentType, 'json')) {
            return null;
        }

        $content = $response->getContent();
        if ($content === false || $content === '') {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
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

        return $request->getMethod().' '.ltrim($uri, '/');
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
