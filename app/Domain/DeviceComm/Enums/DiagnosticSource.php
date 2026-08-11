<?php

namespace App\Domain\DeviceComm\Enums;

/** Origin of a device diagnostic record (DB Arch §6.2; openapi DeviceDiagnostic.source). */
enum DiagnosticSource: string
{
    case ScheduledPing = 'scheduled_ping';
    case OpenDispatch = 'open_dispatch';
    case AdminPing = 'admin_ping';
    case DeviceInitiated = 'device_initiated';
}
