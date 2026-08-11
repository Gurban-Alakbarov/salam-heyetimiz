<?php

namespace App\Domain\Visitor\Enums;

/**
 * How a visitor link is bounded. Both map onto the same two primitives on visitor_links
 * (max_usage + expires_at) — this enum is the resident-facing label the UI creates from.
 */
enum VisitorAccessType: string
{
    /** Valid for a single successful open (max_usage = 1), with a safety expiry so an unused link dies. */
    case OneTime = 'one_time';

    /** Unlimited opens within a time window (expires_at set, max_usage null). */
    case TimeLimited = 'time_limited';
}
