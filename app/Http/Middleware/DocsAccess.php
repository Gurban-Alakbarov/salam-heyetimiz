<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the API documentation surface (R-DOCS-01).
 *
 * When config('docs.enabled') is false the docs are hidden entirely (404). When
 * config('docs.protect') is true (default in production) every docs route — the rendered
 * UIs AND the raw spec — sits behind HTTP Basic Auth so anonymous users cannot inspect the
 * API. The JWT "Try it out" flow is unaffected: the bearer token is supplied inside Swagger
 * UI's Authorize dialog and travels on the API call, not on the docs request.
 */
class DocsAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('docs.enabled', true)) {
            abort(404);
        }

        if (! config('docs.protect', false)) {
            return $next($request);
        }

        $expectedUser = (string) config('docs.username');
        $expectedPass = (string) config('docs.password');

        // Fail-closed: protection on but no password configured → nobody gets in.
        $authorized = $expectedPass !== ''
            && hash_equals($expectedUser, (string) $request->getUser())
            && hash_equals($expectedPass, (string) $request->getPassword());

        if (! $authorized) {
            return response('Authentication required.', 401, [
                'WWW-Authenticate' => 'Basic realm="'.config('docs.title', 'API Docs').'", charset="UTF-8"',
            ]);
        }

        return $next($request);
    }
}
