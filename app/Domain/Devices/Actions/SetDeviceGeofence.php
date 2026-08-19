<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Devices\Exceptions\GeofenceConfigException;
use App\Domain\Devices\Models\Device;

/**
 * Set (or clear) a device's geofence — the SINGLE geofence-config rule, reused by the admin device
 * update AND the owner-facing endpoint (GEOFENCE-1 D5). Enabling requires a positive radius and the
 * device to already have coordinates; disabling clears the radius. It NEVER touches coordinates,
 * ownership, roster, or subscription — geofencing is an additional restriction, not a replacement for
 * R-DOM-05.
 */
final class SetDeviceGeofence
{
    public function handle(Device $device, bool $enabled, ?int $radiusM): Device
    {
        if ($enabled) {
            if ($radiusM === null || $radiusM <= 0) {
                throw GeofenceConfigException::radiusRequired();
            }
            if ($device->latitude === null || $device->longitude === null) {
                throw GeofenceConfigException::deviceLocationMissing();
            }
            $device->forceFill(['geofence_enabled' => true, 'geofence_radius_m' => $radiusM]);
        } else {
            $device->forceFill(['geofence_enabled' => false, 'geofence_radius_m' => null]);
        }

        $device->save();

        return $device;
    }
}
