<?php

namespace App\Domain\DeviceComm\Enums;

/**
 * Direction of a relay command (Phase 4). A device driver resolves the command text per direction from
 * the device model (open_command / close_command). `open` is the historical default (the pipeline was
 * open-only before v1.3); barrier control adds `close` so a press can latch the relay back.
 */
enum CommandDirection: string
{
    case Open = 'open';
    case Close = 'close';

    /** Azerbaijani label for admin/audit surfaces. */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Açılış',
            self::Close => 'Bağlanış',
        };
    }
}
