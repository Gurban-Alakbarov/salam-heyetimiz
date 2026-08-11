<?php

namespace App\Domain\Devices\Enums;

/**
 * Device-communication driver (R-GSM-01, v1.2 transport pivot — UMKa 310 / Traccar):
 *   - `traccar` — primary, remote + in-person (Traccar custom command → cmdout.p / OUTPUT0).
 *   - `sms`     — emergency fallback (device SMS command).
 *   - `ble`     — reserved but deferred off the MVP critical path (FINAL_PHASE0_VERDICT.md).
 */
enum DriverType: string
{
    case Traccar = 'traccar';
    case Sms = 'sms';
    case Ble = 'ble';

    /**
     * Whether the driver can observe an actuation signal (R-GSM-03 / CRIT-06). Only such drivers may
     * reach the terminal `opened` state. Traccar reads the device output bit back from telemetry; SMS
     * is fire-and-forget (terminates at `dispatched`); BLE is deferred.
     */
    public function confirmsActuation(): bool
    {
        return match ($this) {
            self::Traccar => true,
            self::Sms, self::Ble => false,
        };
    }
}
