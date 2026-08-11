<?php

namespace App\Domain\DeviceComm\Enums;

/** Whitelist outbox row status (DB Arch §6.3 `status`). Drained by the WhitelistSyncJob (GSM batch). */
enum WhitelistChangeStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Synced = 'synced';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
