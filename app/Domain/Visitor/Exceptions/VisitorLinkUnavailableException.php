<?php

namespace App\Domain\Visitor\Exceptions;

use App\Domain\Visitor\Enums\VisitorUsageResult;
use App\Exceptions\Contracts\DomainException;

/**
 * A visitor link cannot be used right now. Carries the machine reason (also written to
 * visitor_link_usages) so the renderer maps it to the standard error envelope with no handler edits.
 */
final class VisitorLinkUnavailableException extends DomainException
{
    public function __construct(public readonly VisitorUsageResult $reason)
    {
        parent::__construct($this->errorCode());
    }

    public function httpStatus(): int
    {
        return match ($this->reason) {
            VisitorUsageResult::NotFound => 404,
            VisitorUsageResult::Expired => 410,
            VisitorUsageResult::DeviceInactive => 409,
            default => 403, // revoked, usage_exceeded
        };
    }

    public function errorCode(): string
    {
        return match ($this->reason) {
            VisitorUsageResult::NotFound => 'visitor_link_not_found',
            VisitorUsageResult::Expired => 'visitor_link_expired',
            VisitorUsageResult::Revoked => 'visitor_link_revoked',
            VisitorUsageResult::UsageExceeded => 'visitor_link_usage_exceeded',
            VisitorUsageResult::DeviceInactive => 'visitor_device_inactive',
            default => 'visitor_link_unavailable',
        };
    }

    public function messageKey(): string
    {
        return 'errors.'.$this->errorCode();
    }
}
