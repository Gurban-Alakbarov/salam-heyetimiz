<?php

namespace App\Domain\Devices\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceDecommissioned;
use App\Domain\Devices\Events\DeviceDisabled;
use App\Domain\Devices\Events\DeviceEnabled;
use App\Domain\Devices\Jobs\RevokeDeviceRosterJob;
use App\Domain\Devices\Models\Device;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Device-wide status transitions (R-DOM-06): disable (block all opens, keep history), re-enable, and
 * decommission (terminal soft-delete). These are the device-level states — per-caller suspension is a
 * separate derived concept (R-DOM-07). Whitelist consequences are DeviceComm's reaction (batch 09).
 */
final class DeviceStatusService
{
    public function __construct(private readonly Clock $clock) {}

    public function disable(Device $device, ?string $reason, AdminUser $admin): Device
    {
        $device->forceFill([
            'status' => DeviceStatus::Disabled->value,
            'updated_by_admin_id' => $admin->getKey(),
        ])->save();

        DeviceDisabled::dispatch($device, $reason);

        return $device;
    }

    public function enable(Device $device, AdminUser $admin): Device
    {
        // Re-enable restores the device to its assignment-derived state.
        $restored = $device->owner_user_id !== null ? DeviceStatus::Active : DeviceStatus::Unassigned;

        $device->forceFill([
            'status' => $restored->value,
            'updated_by_admin_id' => $admin->getKey(),
        ])->save();

        DeviceEnabled::dispatch($device);

        return $device;
    }

    public function decommission(Device $device, string $reason, AdminUser $admin): void
    {
        $deviceId = (int) $device->getKey();

        DB::transaction(function () use ($device, $reason, $admin): void {
            $device->forceFill([
                'status' => DeviceStatus::Decommissioned->value,
                'decommissioned_at' => $this->clock->now(),
                'decommission_reason' => $reason,
                'updated_by_admin_id' => $admin->getKey(),
            ])->save();

            $device->delete(); // soft delete (SoftDeletes)
        });

        // Revoke the roster asynchronously (append-logged) and emit for the audit/DeviceComm reactors.
        RevokeDeviceRosterJob::dispatch($deviceId, (int) $admin->getKey());
        DeviceDecommissioned::dispatch($deviceId, $reason);
    }
}
