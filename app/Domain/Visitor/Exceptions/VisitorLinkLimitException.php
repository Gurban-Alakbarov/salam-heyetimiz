<?php

namespace App\Domain\Visitor\Exceptions;

use App\Exceptions\Contracts\DomainException;

/**
 * Too many active visitor links already exist for this creator or this device (anti-abuse cap,
 * config domain.visitor.max_active_per_*). The caller must revoke or let some expire before creating more.
 */
final class VisitorLinkLimitException extends DomainException
{
    /** @param 'creator'|'device' $scope */
    public function __construct(
        public readonly string $scope,
        public readonly int $limit,
    ) {
        parent::__construct('visitor_link_limit_reached');
    }

    public function httpStatus(): int
    {
        return 409;
    }

    public function errorCode(): string
    {
        return 'visitor_link_limit_reached';
    }

    public function messageKey(): string
    {
        return 'errors.visitor_link_limit_reached';
    }

    /**
     * @return array<string, mixed>
     */
    public function details(): array
    {
        return ['scope' => $this->scope, 'limit' => $this->limit];
    }
}
