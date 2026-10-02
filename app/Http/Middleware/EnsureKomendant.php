<?php

namespace App\Http\Middleware;

use App\Domain\Admin\Services\KomendantContext;
use App\Domain\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for /v1/komendant/* (IMPLEMENTATION_PLAN B5). Runs after auth:user; admits only a mobile user linked
 * to an ACTIVE complex_manager with an assigned complex, and exposes that manager + complex to controllers
 * via request attributes. Everything the Komendant touches is then scoped to that complex server-side —
 * a complex id from the client is never trusted.
 */
class EnsureKomendant
{
    public const ATTR_MANAGER = 'komendant.manager';

    public function __construct(private readonly KomendantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $manager = $user instanceof User ? $this->context->managerFor($user) : null;

        if ($manager === null) {
            $requestId = Context::get('request_id');

            return response()->json(['error' => [
                'code' => 'not_komendant',
                'message_key' => 'errors.forbidden',
                'message' => 'Bu bölmə yalnız kompleks komendantı üçündür.',
                'details' => null,
                'request_id' => is_string($requestId) ? $requestId : null,
            ]], 403);
        }

        $request->attributes->set(self::ATTR_MANAGER, $manager);

        return $next($request);
    }
}
