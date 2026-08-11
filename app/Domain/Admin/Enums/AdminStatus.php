<?php

namespace App\Domain\Admin\Enums;

enum AdminStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Offboarded = 'offboarded';
}
