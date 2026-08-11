<?php

namespace App\Domain\DeviceComm\Enums;

/**
 * Open-command lifecycle states (R-GSM-06; DB Arch §6.1). Exactly these six. `opened` is permitted
 * only for drivers that confirm actuation (R-GSM-03); CLIP-only opens terminate at `dispatched`.
 */
enum OpenCommandState: string
{
    case Queued = 'queued';
    case Dispatching = 'dispatching';
    case Dispatched = 'dispatched';
    case Opened = 'opened';
    case Failed = 'failed';
    case Expired = 'expired';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Dispatched, self::Opened, self::Failed, self::Expired], true);
    }

    public function isSuccess(): bool
    {
        return in_array($this, [self::Dispatched, self::Opened], true);
    }
}
