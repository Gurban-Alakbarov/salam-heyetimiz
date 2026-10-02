<?php

namespace App\Domain\Applications\Exceptions;

use RuntimeException;

/** Business refusals of the registration-application workflow (B9) — mapped 1:1 to HTTP by the controllers. */
final class ApplicationException extends RuntimeException
{
    private function __construct(public readonly string $errorCode, string $message, public readonly int $status)
    {
        parent::__construct($message);
    }

    public static function accountTypeMismatch(): self
    {
        return new self('account_type_mismatch', 'Hesab növünüz bu müraciət növünə uyğun deyil.', 409);
    }

    public static function alreadyOpen(): self
    {
        return new self('application_already_open', 'Sizin artıq açıq müraciətiniz var.', 409);
    }

    public static function duplicateVoen(): self
    {
        return new self('application_duplicate_voen', 'Bu VÖEN ilə gözləmədə olan müraciət artıq mövcuddur.', 409);
    }

    public static function notEditable(): self
    {
        return new self('application_not_editable', 'Müraciət artıq baxılır və dəyişdirilə bilməz.', 409);
    }

    public static function invalidTransition(string $from, string $to): self
    {
        return new self('application_invalid_transition', "Status {$from} → {$to} keçidi mümkün deyil.", 422);
    }

    public static function notPending(): self
    {
        return new self('application_not_pending', 'Müraciət artıq baxılıb.', 409);
    }

    public static function invalidDevice(): self
    {
        return new self('application_invalid_device', 'Cihaz şəxsi (private) rejimdə olmalıdır.', 422);
    }
}
