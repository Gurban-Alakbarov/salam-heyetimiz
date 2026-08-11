<?php

namespace App\Domain\Auth\Support;

/** Masks an email for audit/logging — keeps the first two local chars + the domain (R-SEC-15). */
final class EmailMask
{
    public static function mask(string $email): string
    {
        [$local, $domain] = array_pad(explode('@', $email, 2), 2, '');
        $head = mb_substr($local, 0, 2);
        $masked = $head.str_repeat('*', max(1, mb_strlen($local) - 2));

        return $domain !== '' ? $masked.'@'.$domain : $masked;
    }
}
