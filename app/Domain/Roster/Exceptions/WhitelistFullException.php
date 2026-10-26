<?php

namespace App\Domain\Roster\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * A10 — the device's whitelist is full: its model holds `whitelist_capacity` phone numbers (default 100) and
 * every active roster row takes one. Adding one more would leave that person unable to open the barrier → 409.
 */
final class WhitelistFullException extends DomainException
{
    public function __construct(private readonly int $capacity)
    {
        parent::__construct('The device whitelist is full.');
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'whitelist_full';
    }

    public function messageKey(): string
    {
        return 'errors.whitelist_full';
    }

    public function details(): ?array
    {
        return ['capacity' => $this->capacity];
    }
}
