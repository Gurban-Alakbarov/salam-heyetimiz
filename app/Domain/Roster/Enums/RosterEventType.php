<?php

namespace App\Domain\Roster\Enums;

/**
 * Append-only roster history events (DB Arch §3.3 device_user_history).
 */
enum RosterEventType: string
{
    case Added = 'added';
    case Revoked = 'revoked';
    case RoleChanged = 'role_changed';
    case ReAdded = 're_added';
}
