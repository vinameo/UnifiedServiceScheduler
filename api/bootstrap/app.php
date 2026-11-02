<?php

use App\Http\Middleware\CorrelationId;
use App\Support\Problem\ProblemException;
use App\Support\Problem\ProblemRenderer;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        then: function () {
            Route::group([], __DIR__.'/../routes/health.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(CorrelationId::class);
        // Sanctum SPA mode: cookie sessions + CSRF for requests from SANCTUM_STATEFUL_DOMAINS.
        $middleware->statefulApi();
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Business outcomes (409, 412, ...) are answers, not failures to alert on.
        $exceptions->dontReport(ProblemException::class);

        // API only: every error is RFC 9457 problem+json.
        $exceptions->render(
            fn (Throwable $e, Request $request) => ProblemRenderer::toProblem($e)->toResponse($request),
        );
    })->create();
