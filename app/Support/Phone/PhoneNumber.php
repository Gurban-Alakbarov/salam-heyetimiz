<?php

namespace App\Support\Phone;

use InvalidArgumentException;

/**
 * Azerbaijani phone number, canonicalised to E.164 (R-CODE-11, MED-03). The API
 * contract is `^\+994\d{9}$`; common input forms are normalised before storage.
 * Country is fixed to AZ (+994) for MVP.
 */
final readonly class PhoneNumber
{
    public const PATTERN = '/^\+994\d{9}$/';

    private function __construct(public string $e164) {}

    public static function fromInput(string $raw): self
    {
        $e164 = self::normalise($raw);

        if ($e164 === null) {
            throw new InvalidArgumentException("Invalid AZ phone number: {$raw}");
        }

        return new self($e164);
    }

    public static function tryFromInput(?string $raw): ?self
    {
        if ($raw === null) {
            return null;
        }

        $e164 = self::normalise($raw);

        return $e164 === null ? null : new self($e164);
    }

    /** Mask for display: +994 50 *** 45 67 (Tech Spec §16.4 / UI privacy). */
    public function masked(): string
    {
        $local = substr($this->e164, 4); // 9 digits after +994

        return sprintf(
            '+994 %s *** %s %s',
            substr($local, 0, 2),
            substr($local, 5, 2),
            substr($local, 7, 2),
        );
    }

    public function __toString(): string
    {
        return $this->e164;
    }

    private static function normalise(string $raw): ?string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        // 0XXXXXXXXX (national trunk) → strip leading 0
        if (strlen($digits) === 10 && str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        // 994XXXXXXXXX → drop country code to local
        if (strlen($digits) === 12 && str_starts_with($digits, '994')) {
            $digits = substr($digits, 3);
        }

        // Now expect exactly 9 local digits.
        if (strlen($digits) !== 9) {
            return null;
        }

        $e164 = '+994'.$digits;

        return preg_match(self::PATTERN, $e164) === 1 ? $e164 : null;
    }
}
