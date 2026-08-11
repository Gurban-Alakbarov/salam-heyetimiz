<?php

namespace App\Domain\Devices\Services;

use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;

/**
 * Read-only Devices facade exposed for other modules (BACKEND §14.4). Lets Payments enforce R-DOM-13
 * and Subscriptions read device status for the open-permission/eligibility check (R-ARCH-06) without
 * reaching into Devices' models.
 */
final class DeviceLookup
{
    /** True if the device exists and is in `unassigned` state (ready to be sold). */
    public function isUnassigned(int $deviceId): bool
    {
        return Device::query()
            ->whereKey($deviceId)
            ->where('status', DeviceStatus::Unassigned->value)
            ->exists();
    }

    /** Current device status, or null if the device does not exist. */
    public function status(int $deviceId): ?DeviceStatus
    {
        $value = Device::query()->whereKey($deviceId)->value('status');

        return $value instanceof DeviceStatus ? $value : null;
    }

    /** The device's owner user id, or null when the device is unowned or absent. */
    public function ownerUserId(int $deviceId): ?int
    {
        $value = Device::query()->whereKey($deviceId)->value('owner_user_id');

        return $value === null ? null : (int) $value;
    }

    /** The device's short display label (e.g. "Blok A qapısı"), or null when unset/absent. */
    public function locationLabel(int $deviceId): ?string
    {
        $value = Device::query()->whereKey($deviceId)->value('location_label');

        return $value === null ? null : (string) $value;
    }
}
