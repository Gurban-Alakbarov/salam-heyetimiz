<?php

namespace App\Http\Middleware;

use App\Support\Enums\Locale;
use App\Support\Locale\LocaleResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale (R-LOC-08): explicit profile preference →
 * Accept-Language ∩ supported → default `az`. Sets the app locale for the
 * request lifetime. Error/content language remains key-based (R-LOC-02).
 */
class LocaleNegotiator
{
    public function __construct(private readonly LocaleResolver $resolver) {}

    public function handle(Request $request, Closure $next): Response
    {
        $preferred = $request->user()?->preferred_language ?? null;

        if ($preferred instanceof Locale) {
            $preferred = $preferred->value;
        }

        $locale = $this->resolver->negotiate(
            $request->header('Accept-Language'),
            is_string($preferred) ? $preferred : null,
        );

        app()->setLocale($locale->value);

        return $next($request);
    }
}
