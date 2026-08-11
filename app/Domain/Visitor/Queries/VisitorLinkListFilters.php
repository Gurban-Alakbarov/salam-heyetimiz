<?php

namespace App\Domain\Visitor\Queries;

/**
 * Optional server-side filters for the admin visitor-links directory. Every field is nullable; a null
 * leaves that dimension unfiltered, so the default (all-null) reproduces the original unfiltered listing.
 */
final readonly class VisitorLinkListFilters
{
    public function __construct(
        public ?int $deviceId = null,
        public ?string $status = null,        // active | expired | revoked
        public ?string $q = null,             // visitor_name search (partial)
        public ?string $purpose = null,       // VisitorPurpose value
        public ?string $accessType = null,    // one_time | time_limited
        public ?string $createdBy = null,     // 'admin' | 'resident'
        public ?string $createdFrom = null,   // ISO date/datetime (created_at >=)
        public ?string $createdTo = null,     // ISO date/datetime (created_at <=)
    ) {}
}
