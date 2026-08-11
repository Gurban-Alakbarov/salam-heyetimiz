<?php

namespace App\Domain\Devices\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceRegistrationData;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceRegistered;
use App\Domain\Devices\Events\DeviceUpdated;
use App\Domain\Devices\Models\Device;

/**
 * Device record lifecycle (DB Arch §3.1; Tech Spec §3): registration of a new physical unit into
 * `unassigned`, and admin edits of its mutable fields. Ownership, status and transfer live in their
 * own services. Uniqueness of serial/sim_phone is enforced by the request validation + DB constraints.
 */
final class DeviceProvisioningService
{
    public function register(DeviceRegistrationData $data, ?AdminUser $admin): Device
    {
        $device = Device::query()->create([
            'serial' => $data->serial,
            'device_model_id' => $data->deviceModelId,
            'sim_phone' => $data->simPhone,
            'sim_operator_id' => $data->simOperatorId,
            'sim_iccid' => $data->simIccid,
            'driver_type' => $data->driverType,
            'firmware_version' => $data->firmwareVersion,
            'region_id' => $data->regionId,
            'location_label' => $data->locationLabel,
            'image_url' => $data->imageUrl,
            'address' => $data->address,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
            'metadata' => $data->metadata,
            'status' => DeviceStatus::Unassigned->value,
            'registered_by_admin_id' => $admin?->getKey(),
            'created_by_admin_id' => $admin?->getKey(),
        ]);

        DeviceRegistered::dispatch($device);

        return $device;
    }

    /**
     * Apply a partial set of admin-editable columns (DeviceAdminUpdate). Only the keys present in
     * $changes are written; an explicit null clears the column.
     *
     * @param  array<string, mixed>  $changes
     */
    public function update(Device $device, array $changes, ?AdminUser $admin): Device
    {
        if ($changes === []) {
            return $device;
        }

        $device->forceFill($changes);
        $device->forceFill(['updated_by_admin_id' => $admin?->getKey()]);
        $device->save();

        DeviceUpdated::dispatch($device, $changes);

        return $device;
    }
}
