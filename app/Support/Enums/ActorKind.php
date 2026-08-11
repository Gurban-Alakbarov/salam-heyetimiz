<?php

namespace App\Support\Enums;

/**
 * Actor kind shared by append-only history/audit rows (DB Arch §3.3, §8.1).
 */
enum ActorKind: string
{
    case User = 'user';
    case Admin = 'admin';
    case System = 'system';
}
