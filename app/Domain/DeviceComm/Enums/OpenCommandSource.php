<?php

namespace App\Domain\DeviceComm\Enums;

/** Origin of an open command (DB Arch §6.1 `source`). */
enum OpenCommandSource: string
{
    case Mobile = 'mobile';
    case Admin = 'admin';
    case Automation = 'automation';
    /** Opened through a resident/admin-issued visitor link (no roster user; the link is the authority). */
    case Visitor = 'visitor';
}
