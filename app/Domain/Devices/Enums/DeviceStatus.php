<?php

namespace App\Domain\Devices\Enums;

enum DeviceStatus: string
{
    case Unassigned = 'unassigned';
    case Active = 'active';
    /** A10 — reserved/legacy: never written by any code path; readers treat it like Disabled. */
    case Suspended = 'suspended';
    case Disabled = 'disabled';
    case Decommissioned = 'decommissioned';
}
