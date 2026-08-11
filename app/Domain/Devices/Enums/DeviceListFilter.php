<?php

namespace App\Domain\Devices\Enums;

/** listMyDevices `filter` query param (openapi). */
enum DeviceListFilter: string
{
    case All = 'all';
    case Owned = 'owned';
    case Member = 'member';
    case Suspended = 'suspended';
}
