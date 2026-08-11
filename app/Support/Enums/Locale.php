<?php

namespace App\Support\Enums;

/**
 * Supported locales (R-LOC-01). `az` is the default & source of truth; `ru` and
 * `en` are secondary. BCP-47, region-agnostic.
 */
enum Locale: string
{
    case Az = 'az';
    case Ru = 'ru';
    case En = 'en';

    public static function default(): self
    {
        return self::Az;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    public static function tryFromString(?string $value): ?self
    {
        if ($value === null) {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }
}
