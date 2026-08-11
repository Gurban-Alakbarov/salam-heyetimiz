<?php

namespace App\Domain\Visitor\DTOs;

use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Enums\VisitorPurpose;

/**
 * Validated input for creating a visitor link. `durationMinutes` applies only to time_limited links; a
 * one_time link ignores it (bounded by max_usage = 1 plus a safety expiry set in the service). `purpose`
 * is optional and reporting-only — it never affects access.
 */
final readonly class CreateVisitorLinkData
{
    public function __construct(
        public VisitorAccessType $accessType,
        public ?string $visitorName = null,
        public ?int $durationMinutes = null,
        public ?VisitorPurpose $purpose = null,
    ) {}
}
