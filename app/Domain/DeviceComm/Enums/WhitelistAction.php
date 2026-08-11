<?php

namespace App\Domain\DeviceComm\Enums;

/** Whitelist outbox mutation kind (DB Arch §6.3 `action`). */
enum WhitelistAction: string
{
    case Add = 'add';
    case Remove = 'remove';
    case Clear = 'clear';
}
