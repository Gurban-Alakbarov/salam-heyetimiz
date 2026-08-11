<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Events\ResidentDeleted;
use App\Domain\Roster\Exceptions\ResidentOwnsDeviceException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Remove a resident from the SYSTEM (adminDeleteResident) — the whole account, not just one device.
 *
 * Order matters: every active membership is revoked THROUGH RosterService so each device still emits
 * RosterUserRemoved (DeviceComm de-whitelists the user on the hardware — R-GSM-07); entitlements are
 * cancelled through SubscriptionService so their events fire too; refresh tokens are revoked so a
 * deleted account cannot refresh its way back in; and the user row is SOFT-deleted (users.deleted_at)
 * — the history in device_user_history / open_commands / orders stays intact and referential.
 *
 * Refuses (409) while the resident still OWNS a device: deleting them would orphan that barrier, so
 * the admin must transfer or decommission it first.
 */
final class DeleteResident
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly SubscriptionService $subscriptions,
        private readonly Clock $clock,
    ) {}

    public function handle(User $user, ?AdminUser $admin): void
    {
        $ownsDevices = Device::query()->where('owner_user_id', $user->getKey())->exists();
        if ($ownsDevices) {
            throw new ResidentOwnsDeviceException();
        }

        $adminId = $admin !== null ? (int) $admin->getKey() : null;

        DB::transaction(function () use ($user, $adminId): void {
            $active = DeviceUser::query()
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->get();

            foreach ($active as $row) {
                /** @var Device|null $device */
                $device = Device::query()->find($row->device_id);
                if ($device !== null) {
                    // Emits RosterUserRemoved → whitelist removal on the barrier.
                    $this->roster->removeMember($device, $user, ActorKind::Admin, $adminId);
                }
            }

            $deviceUserIds = DeviceUser::query()->where('user_id', $user->getKey())->pluck('id')->all();

            $cancelled = 0;
            if ($deviceUserIds !== []) {
                $live = Subscription::query()
                    ->whereIn('device_user_id', $deviceUserIds)
                    ->whereIn('status', [SubscriptionStatus::Active->value, SubscriptionStatus::PendingPayment->value])
                    ->get();

                foreach ($live as $subscription) {
                    $this->subscriptions->cancel($subscription, 'resident_deleted');
                    $cancelled++;
                }
            }

            // Kill live sessions — a soft-deleted account must not be able to refresh back in.
            RefreshToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('revoked_at')
                ->update(['revoked_at' => $this->clock->now()]);

            $user->delete(); // soft delete (users.deleted_at)

            ResidentDeleted::dispatch((int) $user->getKey(), $adminId, $active->count(), $cancelled);
        });
    }
}
