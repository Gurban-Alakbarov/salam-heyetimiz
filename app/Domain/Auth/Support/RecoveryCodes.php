<?php

namespace App\Domain\Auth\Support;

use Illuminate\Support\Facades\Hash;

/**
 * Single-use admin recovery codes (R-SEC-06 / CRIT-09): 8 codes of 10 hex chars,
 * shown once, bcrypt-hashed in admin_users.recovery_codes_hashes; a consumed slot
 * is set to null; regeneration replaces the whole set atomically.
 */
final class RecoveryCodes
{
    /**
     * Generate a fresh set: returns the plaintext codes (shown once) and their hashes.
     *
     * @return array{plaintext: array<int, string>, hashes: array<int, string>}
     */
    public static function generate(int $count = 8, int $lengthBytes = 5): array
    {
        $plaintext = [];
        $hashes = [];

        for ($i = 0; $i < $count; $i++) {
            $code = bin2hex(random_bytes($lengthBytes)); // 5 bytes → 10 hex chars
            $plaintext[] = $code;
            $hashes[] = Hash::make($code);
        }

        return ['plaintext' => $plaintext, 'hashes' => $hashes];
    }

    /**
     * Find the index of the slot matching the candidate code, or null. Case-insensitive
     * (codes are hex). Null/consumed slots are skipped.
     *
     * @param  array<int, string|null>  $hashes
     */
    public static function match(array $hashes, string $candidate): ?int
    {
        $candidate = strtolower(trim($candidate));

        foreach ($hashes as $index => $hash) {
            if (is_string($hash) && $hash !== '' && Hash::check($candidate, $hash)) {
                return $index;
            }
        }

        return null;
    }

    /**
     * @param  array<int, string|null>  $hashes
     */
    public static function remaining(array $hashes): int
    {
        return count(array_filter($hashes, static fn ($h): bool => is_string($h) && $h !== ''));
    }
}
