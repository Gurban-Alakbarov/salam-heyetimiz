<?php

namespace App\Domain\Devices\Enums;

/**
 * SIM lifecycle (HIGH-04). Column is a Phase-1 placeholder; collectors are Phase 2.
 */
enum SimStatus: string
{
    case Active = 'active';
    case LowCredit = 'low_credit';
    case Suspended = 'suspended';
    case Unknown = 'unknown';
}
