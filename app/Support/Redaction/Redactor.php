<?php

namespace App\Support\Redaction;

/**
 * Log/audit redaction (Tech Spec §16.4). Phone & PAN keep last 4; email masks
 * the local part. OTP hashes / passwords are never logged at all (callers must
 * not pass them here). This is display/log redaction; payment_logs additionally
 * uses an allowlist serializer + encryption (R-PAY-10).
 */
final class Redactor
{
    public static function phone(?string $phone): ?string
    {
        if ($phone === null || strlen($phone) <= 4) {
            return $phone;
        }

        return str_repeat('*', strlen($phone) - 4).substr($phone, -4);
    }

    public static function pan(?string $pan): ?string
    {
        if ($pan === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $pan) ?? '';

        return strlen($digits) < 4 ? '****' : '**** '.substr($digits, -4);
    }

    public static function email(?string $email): ?string
    {
        if ($email === null || ! str_contains($email, '@')) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);
        $head = substr($local, 0, 1);

        return $head.str_repeat('*', max(1, strlen($local) - 1)).'@'.$domain;
    }
}
