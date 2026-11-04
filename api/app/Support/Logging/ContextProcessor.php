<?php

namespace App\Support\Logging;

use App\Http\Middleware\CorrelationId;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;
use Throwable;

/**
 * Injects correlationId, userId, dealershipId, appointmentId, and outcome
 * into Monolog log record context (D-08.1, INV-08.2, AC-08.5).
 */
final class ContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $context = $record->context;
        $request = $this->currentRequest();

        // 1. correlationId: preserve if present, resolve from request or shared context, or generate
        if (! isset($context['correlationId']) || ! is_string($context['correlationId']) || $context['correlationId'] === '') {
            $context['correlationId'] = $this->resolveCorrelationId($request);
        }

        // 2. userId & dealershipId from authenticated user
        if (app()->bound('auth')) {
            try {
                if (Auth::check()) {
                    $user = Auth::user();
                    if ($user !== null) {
                        if (! isset($context['userId'])) {
                            $context['userId'] = (string) $user->getAuthIdentifier();
                        }
                        if (! isset($context['dealershipId']) && isset($user->dealership_id)) {
                            $context['dealershipId'] = (string) $user->dealership_id;
                        }
                    }
                }
            } catch (Throwable) {
                // Ignore auth resolution errors during early boot or testing
            }
        }

        // 3. dealershipId from request/route if not already set
        if (! isset($context['dealershipId']) && $request !== null) {
            $dealership = $request->route('dealership');
            if ($dealership !== null) {
                $context['dealershipId'] = is_object($dealership) && isset($dealership->id)
                    ? (string) $dealership->id
                    : (string) $dealership;
            } elseif ($request->attributes->has('dealershipId')) {
                $context['dealershipId'] = (string) $request->attributes->get('dealershipId');
            }
        }

        // 4. appointmentId from request/route if not already set
        if (! isset($context['appointmentId']) && $request !== null) {
            $appointment = $request->route('appointment') ?? $request->route('id');
            if ($appointment !== null && $this->isAppointmentRoute($request)) {
                $context['appointmentId'] = is_object($appointment) && isset($appointment->id)
                    ? (string) $appointment->id
                    : (string) $appointment;
            } elseif ($request->attributes->has('appointmentId')) {
                $context['appointmentId'] = (string) $request->attributes->get('appointmentId');
            }
        }

        // 5. outcome from request attributes if not already set
        if (! isset($context['outcome']) && $request !== null && $request->attributes->has('outcome')) {
            $context['outcome'] = (string) $request->attributes->get('outcome');
        }

        return $record->with(context: $context);
    }

    private function currentRequest(): ?Request
    {
        if (! app()->bound('request')) {
            return null;
        }

        return request();
    }

    private function resolveCorrelationId(?Request $request): string
    {
        if ($request !== null) {
            $id = CorrelationId::of($request);
            if ($id !== null && $id !== '') {
                return $id;
            }

            $headerId = $request->header(CorrelationId::HEADER)
                ?? $request->header('X-Request-Id');
            if (is_string($headerId) && $headerId !== '') {
                return $headerId;
            }
        }

        try {
            $shared = Log::sharedContext();
            if (isset($shared['correlationId']) && is_string($shared['correlationId']) && $shared['correlationId'] !== '') {
                return $shared['correlationId'];
            }
        } catch (Throwable) {
            // Ignore if Log facade root is unavailable
        }

        return (string) Str::uuid();
    }

    private function isAppointmentRoute(Request $request): bool
    {
        $uri = $request->route()?->uri() ?? $request->path();

        return str_contains($uri, 'appointment');
    }
}
