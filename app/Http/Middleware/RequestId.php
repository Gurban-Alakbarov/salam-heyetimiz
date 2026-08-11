<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Generates/propagates X-Request-Id (ULID) and shares it into the log context
 * for correlation (Backend Arch §6.5 RequestId + LogContext; Tech Spec §16.2).
 */
class RequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->headers->get('X-Request-Id');

        if (! is_string($id) || $id === '') {
            $id = (string) Str::ulid();
        }

        $request->headers->set('X-Request-Id', $id);
        Context::add('request_id', $id);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $id);

        return $response;
    }
}
