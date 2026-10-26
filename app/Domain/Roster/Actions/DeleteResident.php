<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Events\ResidentDeleted;
use App\Domain\Roster\Exceptions\ResidentOwnsDeviceException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\FamilyService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Exceptions\SuccessorRequiredException;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountAnonymiser;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Remove an account from the SYSTEM — by an admin (adminDeleteResident) or by its owner from the app
 * (deleteMyAccount, Y7 — required by the App Store / Google Play). The whole account, not just one device.
 *
 * Order matters: every active membership is revoked THROUGH RosterService so each device still emits
 * RosterUserRemoved (DeviceComm de-whitelists the user on the hardware — R-GSM-07; their visitor links are
 * revoked — K7); entitlements are cancelled through SubscriptionService so their events fire too (no refund,
 * BR-18); refresh tokens are revoked so a deleted account cannot refresh its way back in; the row is
 * ANONYMISED (Y7 / HIGH-12 — phone, email, name, IP, push tokens; the phone and email are free to register
 * again — Y3) and SOFT-deleted (users.deleted_at) — the history in device_user_history / open_commands /
 * orders stays intact and referential, just no longer personal.
 *
 * Also undone, like any other loss of access: the family this account heads (its members lose the access
 * granted through it — removed_by_family_head), its own family / complex memberships, its pending family
 * invitations, and a Komendant (admin) link.
 *
 * Refuses (409) while the account still OWNS a device: deleting it would orphan that barrier, so the device
 * is transferred or decommissioned first (admin: resident_owns_device; self-service: successor_required).
 */
final class DeleteResident
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly SubscriptionService $subscriptions,
        private readonly FamilyService $family,
        private readonly ComplexMembershipService $memberships,
        private readonly InvitationService $invitations,
        private readonly AccountAnonymiser $anonymiser,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /** @param  bool  $selfService  true when the account owner deletes their own account from the app */
    public function handle(User $user, ?AdminUser $admin, bool $selfService = false): void
    {
        $ownsDevices = Device::query()->where('owner_user_id', $user->getKey())->exists();
        if ($ownsDevices) {
            throw $selfService ? new SuccessorRequiredException : new ResidentOwnsDeviceException;
        }

        $adminId = $admin !== null ? (int) $admin->getKey() : null;
        $byUser = $selfService ? $user : null;

        DB::transaction(function () use ($user, $admin, $adminId, $byUser, $selfService): void {
            // The family this account heads: members lose the access granted through it (cancelled, no refund).
            $headed = FamilyLink::query()->with('member')
                ->where('head_user_id', $user->getKey())
                ->where('status', FamilyLinkStatus::Active->value)
                ->get();
            foreach ($headed as $link) {
                if ($link->member !== null) {
                    $this->family->removeMember($user, $link->member);
                }
            }

            $active = DeviceUser::query()
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->get();

            foreach ($active as $row) {
                /** @var Device|null $device */
                $device = Device::query()->find($row->device_id);
                if ($device !== null) {
                    // Emits RosterUserRemoved → whitelist removal on the barrier.
                    $this->roster->removeMember($device, $user, $selfService ? ActorKind::User : ActorKind::Admin, $selfService ? (int) $user->getKey() : $adminId);
                }
            }

            // Its own family memberships (as a member) end with it.
            FamilyLink::query()
                ->where('member_user_id', $user->getKey())
                ->where('status', FamilyLinkStatus::Active->value)
                ->update([
                    'status' => FamilyLinkStatus::Removed->value,
                    'removed_at' => $this->clock->now(),
                    'removed_by_user_id' => $byUser?->getKey(),
                ]);

            foreach ($this->memberships->complexIdsFor((int) $user->getKey()) as $complexId) {
                $this->memberships->remove($complexId, $user, $byUser, $admin);
            }

            Invitation::query()
                ->where('invited_by_user_id', $user->getKey())
                ->where('status', InvitationStatus::Pending->value)
                ->get()
                ->each(fn (Invitation $invitation) => $this->invitations->revoke($invitation, $byUser, $admin));

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

            // A Komendant's mobile link: the admin account stays, it just no longer has this mobile login.
            $unlinked = AdminUser::query()->where('user_id', $user->getKey())->pluck('id')->all();
            if ($unlinked !== []) {
                AdminUser::query()->whereKey($unlinked)->update(['user_id' => null]);
                $this->audit->record('admin.mobile_user_unlinked', [
                    'admin_ids' => $unlinked, 'previous_user_id' => (int) $user->getKey(), 'reason' => 'account_deleted',
                ]);
            }

            if ($selfService) {
                $user->forceFill(['status' => UserStatus::SelfDeleted->value]);
            }
            $hashes = $this->anonymiser->anonymise($user);
            $user->delete(); // soft delete (users.deleted_at)

            ResidentDeleted::dispatch((int) $user->getKey(), $adminId, $active->count(), $cancelled, $selfService, $hashes);
        });
    }
}
