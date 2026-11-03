<?php

namespace App\Http\Middleware;

use App\Http\ExpectedVersion;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IfMatch
{
    public function handle(Request $request, Closure $next): Response
    {
        $version = ExpectedVersion::from($request);
        $request->attributes->set('expectedVersion', $version);

        return $next($request);
    }
}
