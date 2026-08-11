<?php

namespace App\Support\Pagination;

/**
 * Opaque cursor for cursor-based pagination (R-API-06). Encodes a monotonic
 * integer key (typically a row id) so clients treat it as an opaque token.
 */
final readonly class Cursor
{
    public static function encode(int $key): string
    {
        return rtrim(strtr(base64_encode((string) $key), '+/', '-_'), '=');
    }

    public static function decode(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }

        $decoded = base64_decode(strtr($cursor, '-_', '+/'), true);

        if ($decoded === false || ! ctype_digit($decoded)) {
            return null;
        }

        return (int) $decoded;
    }
}
