<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Stashes the RAW request bytes before any JSON parsing so HMAC verifiers hash the exact wire
 * bytes, never a re-encoded body (CRIT-07 / R-PAY-03). Runs first in the webhook stack. Nginx must
 * be configured `proxy_request_buffering on;` with no body-rewriting on this path.
 */
class CaptureRawBody
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('raw_body', $request->getContent());

        return $next($request);
    }
}
