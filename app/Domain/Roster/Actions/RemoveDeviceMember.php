<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Actions\CancelOnAccessRemoval;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\DB;

/**
 * Remove a resident from a device roster (admin panel). BR-18, as on the komendant / family / account-deletion
 * paths: the resident's live subscription on this device is cancelled (no automatic refund), so re-adding them
 * later never revives the old paid time. Moving a resident to another device is a different action
 * (MoveDeviceMember) and keeps the subscription.
 */
final class RemoveDeviceMember
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly CancelOnAccessRemoval $cancelOnRemoval,
    ) {}

    public function handle(Device $device, int $userId, AdminUser $admin): void
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);
        $adminId = (int) $admin->getKey();

        DB::transaction(function () use ($device, $user, $adminId): void {
            $this->roster->removeMember($device, $user, ActorKind::Admin, $adminId);

            /** @var DeviceUser|null $row the row removeMember just revoked */
            $row = DeviceUser::query()
                ->where('device_id', $device->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Revoked->value)
                ->latest('revoked_at')
                ->first();

            if ($row !== null) {
                $this->cancelOnRemoval->handle($row, 'removed_by_admin', ['removed_by_admin_id' => $adminId]);
            }
        });
    }
}
