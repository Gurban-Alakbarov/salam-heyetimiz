<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Exceptions\RosterMemberNotFoundException;
use App\Domain\Roster\Exceptions\RosterMoveException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Y5 — an admin moves a resident from one gate to another (e.g. to another entrance of the same complex) and
 * the resident does NOT pay again: their remaining paid time moves with them (SubscriptionService::moveTo).
 * The old gate's access is revoked through RosterService (whitelist removal, guest links revoked — K7), the
 * new one is granted the same way, all in one transaction. Without a live subscription only the access moves.
 *
 * Only a plain resident row moves (not an owner, not a family member — their access follows the head). A complex
 * target needs a member of that complex; a target the resident already pays for is refused (409), not merged.
 */
final class MoveDeviceMember
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly SubscriptionService $subscriptions,
        private readonly ComplexMembershipService $memberships,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /** @return array{row: DeviceUser, subscription: ?Subscription} the resident's row on the target gate */
    public function handle(Device $from, User $user, Device $to, AdminUser $admin): array
    {
        if ((int) $from->getKey() === (int) $to->getKey()) {
            throw RosterMoveException::sameDevice();
        }

        /** @var DeviceUser|null $row */
        $row = DeviceUser::query()->where('device_id', $from->getKey())->where('user_id', $user->getKey())
            ->where('status', DeviceUserStatus::Active->value)->first();
        if ($row === null) {
            throw new RosterMemberNotFoundException;
        }
        if ($row->role !== DeviceUserRole::User || $row->family_link_id !== null) {
            throw RosterMoveException::notMovable();
        }
        if ($to->status !== DeviceStatus::Active) {
            throw RosterMoveException::targetInactive();
        }
        if ($to->ownership_mode === DeviceOwnershipMode::Complex
            && ($to->complex_id === null || ! $this->memberships->isMember((int) $to->complex_id, (int) $user->getKey()))) {
            throw RosterMoveException::notComplexMember();
        }

        $now = $this->clock->now();
        $targetRow = DeviceUser::query()->where('device_id', $to->getKey())->where('user_id', $user->getKey())
            ->where('status', DeviceUserStatus::Active->value)->first();
        if ($targetRow !== null && Subscription::query()->where('device_user_id', $targetRow->getKey())
            ->where('status', SubscriptionStatus::Active->value)->where('ends_at', '>', $now)->exists()) {
            throw RosterMoveException::targetAlreadySubscribed();
        }

        return DB::transaction(function () use ($from, $to, $user, $row, $admin, $now): array {
            /** @var Subscription|null $subscription */
            $subscription = Subscription::query()->where('device_user_id', $row->getKey())->first();
            $live = $subscription !== null
                && $subscription->status === SubscriptionStatus::Active
                && $subscription->ends_at !== null && $subscription->ends_at->greaterThan($now);

            $adminId = (int) $admin->getKey();
            $this->roster->removeMember($from, $user, ActorKind::Admin, $adminId);
            $newRow = $this->roster->addMember($to, $user, DeviceUserRole::User, ActorKind::Admin, $adminId);

            $moved = $live ? $this->subscriptions->moveTo($subscription, $newRow) : null;

            $this->audit->record('resident.moved', [
                'user_id' => (int) $user->getKey(),
                'from_device_id' => (int) $from->getKey(),
                'to_device_id' => (int) $to->getKey(),
                'from_subscription_id' => $subscription?->getKey(),
                'to_subscription_id' => $moved?->getKey(),
                'ends_at' => $moved?->ends_at?->toIso8601String(),
                'subscription_moved' => $moved !== null,
            ], DeviceUser::class, (int) $newRow->getKey());

            return ['row' => $newRow, 'subscription' => $moved];
        });
    }
}
