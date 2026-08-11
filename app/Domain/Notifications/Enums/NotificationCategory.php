<?php

namespace App\Domain\Notifications\Enums;

/**
 * Template category (DB Arch §7.1; INVENTORY §5). Governs the default mute policy: security + billing
 * are forced (a user cannot disable them), marketing is user-mutable, operational is forced. The
 * per-template `is_user_mutable` column is the authoritative switch — this is the default it seeds from.
 */
enum NotificationCategory: string
{
    case Security = 'security';
    case Billing = 'billing';
    case Operational = 'operational';
    case Marketing = 'marketing';

    /** Default mute policy for the category — only marketing may be disabled by a user. */
    public function defaultUserMutable(): bool
    {
        return $this === self::Marketing;
    }
}
