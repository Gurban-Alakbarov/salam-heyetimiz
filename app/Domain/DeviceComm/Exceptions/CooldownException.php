<?php

namespace App\Domain\DeviceComm\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** Open attempted during an active cooldown window → 429 with a Retry-After hint (R-DOM-05/09). */
class CooldownException extends DomainException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct('Open command is in cooldown.');
    }

    public function httpStatus(): int
    {
        return 429;
    }

    public function errorCode(): string
    {
        return 'cooldown';
    }

    public function messageKey(): string
    {
        return 'errors.cooldown';
    }

    public function details(): array
    {
        return ['retry_after_seconds' => $this->retryAfterSeconds];
    }

    public function headers(): array
    {
        return ['Retry-After' => $this->retryAfterSeconds];
    }
}
