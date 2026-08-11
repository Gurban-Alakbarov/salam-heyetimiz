<?php

namespace App\Domain\Notifications\Enums;

/**
 * How an admin campaign selects its recipients (DB Arch §7.5; ADMIN_SPEC). `all_users` = every active
 * user; `user_ids` = an explicit list; `filter` = a stored predicate (`audience_filter`); `complex` =
 * everyone in a residential complex (honours the admin's complex scope). Resolution is Phase 6.
 */
enum CampaignAudienceScope: string
{
    case AllUsers = 'all_users';
    case UserIds = 'user_ids';
    case Filter = 'filter';
    case Complex = 'complex';
}
