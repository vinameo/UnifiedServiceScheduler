<?php

namespace App\Support\Problem;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Turns any exception into a problem+json body. Registered in bootstrap/app.php.
 */
final class ProblemRenderer
{
    public static function toProblem(Throwable $e): Problem
    {
        return match (true) {
            $e instanceof ProblemException => $e->toProblem(),
            $e instanceof ValidationException => new Problem(
                400, 'VALIDATION_ERROR', 'The request is not valid.', extra: ['errors' => $e->errors()],
            ),
            $e instanceof AuthenticationException => new Problem(401, 'UNAUTHENTICATED', 'Please sign in.'),
            $e instanceof AuthorizationException,
            $e instanceof AccessDeniedHttpException => new Problem(403, 'FORBIDDEN', 'You may not do this.'),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => new Problem(404, 'NOT_FOUND', 'Not found.'),
            $e instanceof TokenMismatchException,
            ($e instanceof HttpExceptionInterface && $e->getStatusCode() === 419) => new Problem(
                419, 'CSRF_TOKEN_MISMATCH', 'The page has expired. Reload and try again.',
            ),
            $e instanceof ThrottleRequestsException => new Problem(
                429,
                'TOO_MANY_ATTEMPTS',
                'Too many attempts. Try again later.',
                extra: array_filter(['retryAfterSeconds' => isset($e->getHeaders()['Retry-After']) ? (int) $e->getHeaders()['Retry-After'] : null]),
                headers: self::headers($e),
            ),
            $e instanceof HttpExceptionInterface => new Problem(
                $e->getStatusCode(), 'HTTP_'.$e->getStatusCode(), $e->getMessage() ?: 'Request failed.', headers: self::headers($e),
            ),
            // Never leak internals; the log has the details under the same correlation id.
            default => new Problem(500, 'INTERNAL_ERROR', 'Something went wrong.'),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function headers(HttpExceptionInterface $e): array
    {
        return array_map(fn ($value) => (string) $value, $e->getHeaders());
    }
}
