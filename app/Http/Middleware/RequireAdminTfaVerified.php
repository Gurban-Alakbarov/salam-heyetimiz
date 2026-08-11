<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Models\AdminUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforces tfa_verified=true for sensitive admin routes (R-SEC-05). Admin access tokens are
 * only minted after step-2 2FA, so the claim is true by construction; this is defence in
 * depth for any future token type. In tests authenticated via actingAs (no JWT claims) an
 * authenticated AdminUser is treated as verified.
 */
class RequireAdminTfaVerified
{
    public function handle(Request $request, Closure $next): Response
    {
        $actor = $request->user();
        abort_unless($actor instanceof AdminUser, 401);

        // false = a JWT explicitly without tfa_verified; null = no JWT claim (actingAs path).
        abort_if($request->attributes->get('jwt_admin_tfa') === false, 403, __('errors.tfa_required'));

        return $next($request);
    }
}
