<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInternalMetrics
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // AC-08.7, INV-08.5, D-08.7: Allow only requests through the internal Nginx server
        // with METRICS_INTERNAL fastcgi_param set to 1. Client headers (like X-Forwarded-For
        // or HTTP_METRICS_INTERNAL) must NEVER be trusted.
        if ((string) $request->server->get('METRICS_INTERNAL') !== '1') {
            abort(404);
        }

        return $next($request);
    }
}
