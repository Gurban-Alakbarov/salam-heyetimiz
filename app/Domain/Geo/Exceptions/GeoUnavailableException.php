<?php

namespace App\Domain\Geo\Exceptions;

use App\Exceptions\Contracts\DomainException;

/** GoMap is not configured, unreachable or answered with an error — the map picker keeps working without search. */
final class GeoUnavailableException extends DomainException
{
    public function __construct(private readonly string $reason)
    {
        parent::__construct('Map search is temporarily unavailable.');
    }

    public function httpStatus(): int
    {
        return 503;
    }

    public function errorCode(): string
    {
        return 'geo_unavailable';
    }

    public function messageKey(): string
    {
        return 'errors.geo_unavailable';
    }

    public function details(): ?array
    {
        return ['reason' => $this->reason];
    }
}
