<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives every request one correlation id: in the logs, in problem+json and in the
 * X-Correlation-Id response header. Takes the client's id, then nginx's request id.
 */
final class CorrelationId
{
    public const HEADER = 'X-Correlation-Id';

    private const ATTRIBUTE = 'correlationId';

    private const VALID = '/^[A-Za-z0-9._-]{8,128}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $id = $this->resolve($request);

        $request->attributes->set(self::ATTRIBUTE, $id);
        Log::shareContext([self::ATTRIBUTE => $id]);

        $response = $next($request);
        $response->headers->set(self::HEADER, $id);

        return $response;
    }

    public static function of(Request $request): ?string
    {
        $id = $request->attributes->get(self::ATTRIBUTE);

        return is_string($id) ? $id : null;
    }

    private function resolve(Request $request): string
    {
        foreach ([self::HEADER, 'X-Request-Id'] as $header) {
            $value = $request->headers->get($header);

            if (is_string($value) && preg_match(self::VALID, $value) === 1) {
                return $value;
            }
        }

        foreach (['REQUEST_ID', 'X_REQUEST_ID'] as $serverKey) {
            $value = $request->server->get($serverKey);

            if (is_string($value) && preg_match(self::VALID, $value) === 1) {
                return $value;
            }
        }

        return (string) Str::uuid();
    }
}
