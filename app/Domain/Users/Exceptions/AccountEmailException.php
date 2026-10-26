<?php

namespace App\Domain\Users\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Admin-recorded account email refusals (K1): the email is someone else's, or the account already verified its own. */
final class AccountEmailException extends DomainException
{
    private function __construct(private readonly string $reason, private readonly int $status)
    {
        parent::__construct($reason);
    }

    /** The email already identifies another account (incl. a deleted one — the unique index keeps it). */
    public static function taken(): self
    {
        return new self('email_taken', 409);
    }

    /** A verified email is the account's login identity; an admin never replaces it. */
    public static function alreadyVerified(): self
    {
        return new self('email_already_verified', 409);
    }

    public function httpStatus(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->reason;
    }

    public function messageKey(): string
    {
        return 'errors.'.$this->reason;
    }
}
