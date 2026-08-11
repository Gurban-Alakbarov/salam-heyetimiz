<?php

namespace App\Http\Api\V1\Controllers\Auth;

use App\Domain\Auth\Support\JwtKeyRing;
use Illuminate\Http\JsonResponse;

/**
 * JWKS for admin JWT verification (openapi getJwks; CRIT-04 / R-SEC-08). Admin host only;
 * mobile clients pin their key and do not consume JWKS.
 */
class JwksController
{
    /** GET /.well-known/jwks.json — getJwks (public). */
    public function show(JwtKeyRing $keys): JsonResponse
    {
        return response()->json(['keys' => $keys->adminJwks()]);
    }
}
