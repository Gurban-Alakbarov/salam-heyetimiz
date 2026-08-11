<?php

namespace App\Domain\Visitor\Support;

/**
 * Visitor link token: a 256-bit CSPRNG secret in the URL, stored only as its sha256 hash. The plaintext
 * is returned to the creator exactly once (GitHub-PAT model) and is otherwise unrecoverable. Lookups go
 * by hash; because the token is high-entropy random, an unsalted sha256 is the right primitive here
 * (this is an API-token, not a human password — no bcrypt/argon).
 */
final class VisitorToken
{
    /** @return array{plain: string, hash: string, prefix: string} */
    public static function generate(): array
    {
        $plain = self::base64Url(random_bytes(32)); // 43 url-safe chars, ~256 bits

        return [
            'plain' => $plain,
            'hash' => self::hash($plain),
            'prefix' => substr($plain, 0, 8),
        ];
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    private static function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}
