<?php

namespace App\Domain\Devices\Jobs;

use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\RosterEventType;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\DeviceUserHistory;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Revoke every active roster row for a decommissioned device, append-logging each revocation
 * (R-DOM-11). Runs off the request path because a device may carry many members. Idempotent: only
 * still-active rows are touched.
 */
class RevokeDeviceRosterJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $deviceId,
        public readonly ?int $adminId = null,
    ) {
        $this->onQueue('default');
    }

    public function handle(Clock $clock): int
    {
        $now = $clock->now();
        $revoked = 0;

        DeviceUser::query()
            ->where('device_id', $this->deviceId)
            ->where('status', DeviceUserStatus::Active->value)
            ->get()
            ->each(function (DeviceUser $row) use ($now, &$revoked): void {
                $row->forceFill([
                    'status' => DeviceUserStatus::Revoked->value,
                    'revoked_at' => $now,
                    'revoked_by_admin_id' => $this->adminId,
                ])->save();

                DeviceUserHistory::query()->create([
                    'device_id' => $row->device_id,
                    'user_id' => $row->user_id,
                    'event' => RosterEventType::Revoked->value,
                    'from_role' => $row->role->value,
                    'to_role' => null,
                    'actor_kind' => ActorKind::Admin->value,
                    'actor_id' => $this->adminId,
                ]);

                $revoked++;
            });

        return $revoked;
    }
}
