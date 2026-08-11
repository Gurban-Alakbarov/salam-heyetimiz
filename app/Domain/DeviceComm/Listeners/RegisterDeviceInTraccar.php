<?php

namespace App\Domain\DeviceComm\Listeners;

use App\Domain\DeviceComm\Jobs\RegisterTraccarDeviceJob;
use App\Domain\Devices\Events\DeviceRegistered;

/**
 * A new device was registered → provision it in Traccar (R-ARCH-07: DeviceComm reacts to the Devices
 * events; the Devices module stays unaware of the transport, exactly like the whitelist listeners).
 *
 * Enqueues rather than calling Traccar inline so registration never fails — or waits on — the admin's
 * create request; the job carries the id and is idempotent.
 *
 * `afterCommit` matters: the reconciliation flow registers the device and adopts an EXISTING Traccar
 * device (whose uniqueId may differ from the serial — the UMKa's serial is 22061138, its uniqueId the
 * IMEI) inside one transaction. Deferring to the commit lets the job see that mapping and no-op,
 * instead of racing it and provisioning a second, bogus Traccar device keyed on the serial.
 */
class RegisterDeviceInTraccar
{
    public function handle(DeviceRegistered $event): void
    {
        RegisterTraccarDeviceJob::dispatch((int) $event->device->getKey())->afterCommit();
    }
}
