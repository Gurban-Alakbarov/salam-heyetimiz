<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rejects non-JSON bodies on mutating requests (Backend Arch §6.5). Applied per
 * route via the `enforce.json` alias.
 */
class EnforceJson
{
    public function handle(Request $request, Closure $next): Response
    {
        $mutating = in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);

        if ($mutating && $request->getContent() !== '' && ! $request->isJson()) {
            return response()->json([
                'error' => [
                    'code' => 'unsupported_media_type',
                    'message_key' => 'errors.unsupported_media_type',
                    'message' => __('errors.unsupported_media_type'),
                    'details' => null,
                    'request_id' => is_string(Context::get('request_id')) ? Context::get('request_id') : null,
                ],
            ], 415);
        }

        return $next($request);
    }
}
