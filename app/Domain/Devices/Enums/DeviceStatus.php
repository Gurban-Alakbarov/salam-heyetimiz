<?php

namespace App\Domain\Devices\Enums;

enum DeviceStatus: string
{
    case Unassigned = 'unassigned';
    case Active = 'active';
    case Suspended = 'suspended';
    case Disabled = 'disabled';
    case Decommissioned = 'decommissioned';
}
