<?php

namespace App\Domain\DeviceComm\Jobs;

use App\Domain\DeviceComm\Contracts\TraccarClient;
use App\Domain\DeviceComm\Services\TraccarDeviceMapper;
use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Provisions a newly registered device in Traccar so it is accepted the moment it dials in.
 *
 * Without this the device exists only in Laravel: Traccar rejects the session as an unknown uniqueId
 * (silently — it does not log unknown devices at INFO), so the unit connects forever without ever
 * appearing or going online, and someone has to notice and reconcile it by hand.
 *
 * Runs off the request path on the `device-comm` supervisor (alongside WhitelistSyncJob) so a Traccar
 * outage retries instead of failing the admin's device creation. Carries the device id only, and is
 * idempotent — an already-linked device is a no-op, so retries and re-registrations are safe.
 */
class RegisterTraccarDeviceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $deviceId)
    {
        $this->onQueue('device-comm');
    }

    public function handle(TraccarDeviceMapper $mapper, TraccarClient $traccar): void
    {
        $device = Device::query()->find($this->deviceId);

        // Deleted meanwhile, or not a Traccar-transported device (SMS/BLE never live in Traccar).
        if ($device === null || $device->driver_type !== DriverType::Traccar) {
            return;
        }

        // Already mapped → nothing to do (idempotent on retry / re-register).
        if ($mapper->traccarIdFor($device) !== null) {
            return;
        }

        // The Traccar uniqueId is what the unit reports on login — for our fleet that is the IMEI,
        // which is what the technician types into `serial`.
        $uniqueId = trim((string) $device->serial);
        if ($uniqueId === '') {
            return;
        }

        // If the unit was already created in the Traccar UI, adopt it: registering the same uniqueId
        // twice is rejected by Traccar (registerDevice throws), which would just burn the retries.
        $remote = $traccar->findDeviceByUniqueId($uniqueId);

        $remote !== null
            ? $mapper->adopt($device, $remote->id, $uniqueId)
            : $mapper->register($device, $uniqueId);
    }
}
