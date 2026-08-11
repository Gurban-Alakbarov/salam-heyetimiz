<?php

namespace App\Domain\DeviceComm\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Enums\WhitelistChangeStatus;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;

/**
 * Whitelist outbox (R-GSM-07): roster/ownership changes enqueue `whitelist_changes` rows; the
 * per-device drain (WhitelistSyncJob, GSM batch) applies them in (priority ASC, seq ASC) order. This
 * service only enqueues + tracks; it never talks to a device. Admin-forced resync uses priority 10
 * (drains first); routine changes use 50 (R-GSM-08).
 */
final class WhitelistService
{
    public const PRIORITY_ROUTINE = 50;
    public const PRIORITY_RESYNC = 10;

    /** Sentinel phone for a whole-device clear (the column is NOT NULL). */
    public const CLEAR_MARKER = '*';

    public function enqueue(
        Device $device,
        WhitelistAction $action,
        string $phone,
        int $priority = self::PRIORITY_ROUTINE,
        ?int $deviceUserId = null,
        ?int $requestedByUserId = null,
        ?int $requestedByAdminId = null,
    ): WhitelistChange {
        /** @var WhitelistChange $row */
        $row = WhitelistChange::query()->create([
            'device_id' => $device->getKey(),
            'device_user_id' => $deviceUserId,
            'action' => $action->value,
            'phone' => $phone,
            'priority' => $priority,
            'status' => WhitelistChangeStatus::Pending->value,
            'attempt_count' => 0,
            'requested_by_user_id' => $requestedByUserId,
            'requested_by_admin_id' => $requestedByAdminId,
        ]);

        // seq mirrors the auto-increment id — strictly monotonic, burst-safe (MED-09).
        $row->forceFill(['seq' => $row->getKey()])->save();

        return $row;
    }

    /**
     * Full whitelist resync (adminResyncWhitelist): clear, then add every active roster member, all at
     * priority 10 so they drain ahead of routine changes.
     *
     * @return int number of change rows enqueued
     */
    public function enqueueResync(Device $device, AdminUser $admin): int
    {
        $count = 0;
        $adminId = (int) $admin->getKey();

        $this->enqueue($device, WhitelistAction::Clear, self::CLEAR_MARKER, self::PRIORITY_RESYNC, requestedByAdminId: $adminId);
        $count++;

        DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->with('user:id,phone')
            ->get()
            ->each(function (DeviceUser $deviceUser) use ($device, $adminId, &$count): void {
                $phone = $deviceUser->user?->phone;
                if ($phone === null) {
                    return;
                }
                $this->enqueue($device, WhitelistAction::Add, $phone, self::PRIORITY_RESYNC, (int) $deviceUser->getKey(), requestedByAdminId: $adminId);
                $count++;
            });

        return $count;
    }

    public function pendingCount(int $deviceId): int
    {
        return WhitelistChange::query()
            ->where('device_id', $deviceId)
            ->where('status', WhitelistChangeStatus::Pending->value)
            ->count();
    }
}
