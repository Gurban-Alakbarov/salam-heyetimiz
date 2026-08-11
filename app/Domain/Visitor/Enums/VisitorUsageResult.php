<?php

namespace App\Domain\Visitor\Enums;

/** Outcome of one visitor open attempt (audited on visitor_link_usages). */
enum VisitorUsageResult: string
{
    case Accepted = 'accepted';           // link valid, relay command issued (terminal outcome via the pipeline)
    case Expired = 'expired';             // link past expires_at
    case Revoked = 'revoked';             // link revoked by resident/admin
    case UsageExceeded = 'usage_exceeded'; // max_usage reached
    case DeviceInactive = 'device_inactive'; // device disabled / decommissioned / deleted
    case NotFound = 'not_found';          // token did not resolve
}
