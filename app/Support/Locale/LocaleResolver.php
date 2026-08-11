<?php

namespace App\Support\Locale;

use App\Support\Enums\Locale;

/**
 * Locale negotiation (R-LOC-08). Order: explicit preferred (user/admin profile)
 * → Accept-Language ∩ supported → default `az`. The API never derives content
 * language from Accept-Language for authenticated surfaces (R-LOC-02); this is
 * used for the chrome/locale only.
 */
final class LocaleResolver
{
    public function negotiate(?string $acceptLanguage, ?string $preferred = null): Locale
    {
        if ($preferred !== null && ($explicit = Locale::tryFromString($preferred)) !== null) {
            return $explicit;
        }

        foreach ($this->parseAcceptLanguage($acceptLanguage) as $tag) {
            $primary = strtolower(substr($tag, 0, 2));

            if (($match = Locale::tryFromString($primary)) !== null) {
                return $match;
            }
        }

        return Locale::default();
    }

    /**
     * @return list<string> language tags ordered by descending q-weight
     */
    private function parseAcceptLanguage(?string $header): array
    {
        if ($header === null || trim($header) === '') {
            return [];
        }

        $entries = [];

        foreach (explode(',', $header) as $part) {
            $segments = explode(';', trim($part));
            $tag = trim($segments[0]);

            if ($tag === '' || $tag === '*') {
                continue;
            }

            $q = 1.0;
            if (isset($segments[1]) && str_starts_with(trim($segments[1]), 'q=')) {
                $q = (float) substr(trim($segments[1]), 2);
            }

            $entries[] = ['tag' => $tag, 'q' => $q];
        }

        usort($entries, static fn (array $a, array $b): int => $b['q'] <=> $a['q']);

        return array_map(static fn (array $e): string => $e['tag'], $entries);
    }
}
