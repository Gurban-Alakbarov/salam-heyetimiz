<?php

namespace App\Domain\Roster\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Subscriptions\Actions\CancelOnAccessRemoval;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Services\SubscriptionService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Family links (IMPLEMENTATION_PLAN §14 / B8, BR-1..4, BR-18, BR-19). Three separate layers:
 *  - relation: family_links (head → member, one active per pair);
 *  - access:   device_users(role=user, added_by_user_id=head, family_link_id) per granted device;
 *  - billing:  the member's OWN subscription per device (tier additional, 12 AZN / 30 days) — the head or the
 *              member pays (SubscriptionPaymentAuthorizer).
 * Only a family head (DevicePolicy::manageFamily) grants; a member can never grant, invite or share.
 */
final class FamilyService
{
    public function __construct(
        private readonly InvitationService $invitations,
        private readonly RosterService $roster,
        private readonly SubscriptionService $subscriptions,
        private readonly CancelOnAccessRemoval $cancelOnRemoval,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /**
     * Head invites someone to one device. An existing active family member is granted the device directly
     * (they already accepted the family relation); anyone else gets a family_member invitation.
     *
     * @return array{invitation: Invitation|null, granted: DeviceUser|null}
     */
    public function invite(User $head, Device $device, string $email, string $firstName, string $lastName): array
    {
        $this->assertHead($head, $device);
        $email = mb_strtolower(trim($email));
        if ($email === mb_strtolower((string) $head->email)) {
            throw InvitationException::invalidTarget();
        }

        $existing = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        if ($existing !== null) {
            if ($this->hasActiveRow($device, $existing)) {
                throw InvitationException::alreadyHasAccess();
            }
            $link = $this->activeLink($head, $existing);
            if ($link !== null) {
                return ['invitation' => null, 'granted' => $this->grant($link, $device)];
            }
        }

        ['invitation' => $invitation] = $this->invitations->createFamilyMember($head, $email, $firstName, $lastName, (int) $device->getKey());

        return ['invitation' => $invitation, 'granted' => null];
    }

    /** Pre-flight for a family_member invitation (shared with register / accept — ClaimInvitation). */
    public function assertAcceptable(Invitation $invitation, ?User $member = null): void
    {
        $head = $invitation->invitedBy;
        $device = $invitation->device;
        if ($head === null || $device === null || ! Gate::forUser($head)->allows('manageFamily', $device)) {
            throw InvitationException::notClaimable();
        }
        if ($member !== null) {
            if ((int) $member->getKey() === (int) $head->getKey()) {
                throw InvitationException::invalidTarget();
            }
            if ($this->hasActiveRow($device, $member)) {
                throw InvitationException::alreadyHasAccess();
            }
        }
    }

    /**
     * Acceptance (inside ClaimInvitation's transaction, after the conditional status update):
     * link → device row → the member's own pending subscription.
     *
     * @return array{family_link_id: int, device: array{id: int, label: string|null}, subscription_id: int}
     */
    public function accept(Invitation $invitation, User $member): array
    {
        $this->assertAcceptable($invitation, $member);
        $head = $invitation->invitedBy;

        $link = $this->activeLink($head, $member) ?? FamilyLink::query()->create([
            'head_user_id' => $head->getKey(),
            'member_user_id' => $member->getKey(),
            'status' => FamilyLinkStatus::Active->value,
            'invitation_id' => $invitation->getKey(),
            'linked_at' => $this->clock->now(),
        ]);
        $row = $this->grant($link, $invitation->device);

        return [
            'family_link_id' => (int) $link->getKey(),
            'device' => ['id' => (int) $invitation->device->getKey(), 'label' => $invitation->device->location_label],
            'subscription_id' => (int) Subscription::query()->where('device_user_id', $row->getKey())->value('id'),
        ];
    }

    /**
     * Head removes a member: link → removed, every device row granted through it revoked (whitelist follows),
     * each live subscription cancelled with NO refund (BR-18), audited. Periods / orders are never touched.
     *
     * @return array{revoked_rows: int, cancelled_subscriptions: int}|null  null when no active link
     */
    public function removeMember(User $head, User $member): ?array
    {
        $link = $this->activeLink($head, $member);
        if ($link === null) {
            return null;
        }

        return DB::transaction(function () use ($link, $head, $member): array {
            $link->forceFill([
                'status' => FamilyLinkStatus::Removed->value,
                'removed_at' => $this->clock->now(),
                'removed_by_user_id' => $head->getKey(),
            ])->save();

            $rows = DeviceUser::query()->with('device')
                ->where('family_link_id', $link->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->get();

            $audit = ['family_link_id' => (int) $link->getKey(), 'head_user_id' => (int) $head->getKey(), 'member_user_id' => (int) $member->getKey()];
            $cancelled = 0;
            foreach ($rows as $row) {
                if ($this->cancelOnRemoval->handle($row, 'removed_by_family_head', $audit) !== null) {
                    $cancelled++;
                }
                $this->roster->removeMember($row->device, $member, ActorKind::User, (int) $head->getKey());
            }

            $this->audit->record('family.member_removed', $audit + [
                'revoked_rows' => $rows->count(),
                'cancelled_subscriptions' => $cancelled,
            ], FamilyLink::class, (int) $link->getKey());

            return ['revoked_rows' => $rows->count(), 'cancelled_subscriptions' => $cancelled];
        });
    }

    public function activeLink(User $head, User $member): ?FamilyLink
    {
        return FamilyLink::query()
            ->where('head_user_id', $head->getKey())
            ->where('member_user_id', $member->getKey())
            ->where('status', FamilyLinkStatus::Active->value)
            ->first();
    }

    /** Grant one device to an active link's member + open their own pending subscription (reopened if cancelled — B2). */
    private function grant(FamilyLink $link, Device $device): DeviceUser
    {
        return DB::transaction(function () use ($link, $device): DeviceUser {
            $member = $link->member;
            $row = $this->roster->addMember($device, $member, DeviceUserRole::User, ActorKind::User, (int) $link->head_user_id, (int) $link->getKey());
            $this->subscriptions->createPending($row, SubscriptionTier::Additional);

            $this->audit->record('family.device_granted', [
                'family_link_id' => (int) $link->getKey(),
                'device_id' => (int) $device->getKey(),
                'member_user_id' => (int) $member->getKey(),
                'head_user_id' => (int) $link->head_user_id,
            ], FamilyLink::class, (int) $link->getKey());

            return $row;
        });
    }

    private function assertHead(User $head, Device $device): void
    {
        Gate::forUser($head)->authorize('manageFamily', $device);
    }

    private function hasActiveRow(Device $device, User $user): bool
    {
        return DeviceUser::query()->where('device_id', $device->getKey())->where('user_id', $user->getKey())
            ->where('status', DeviceUserStatus::Active->value)->exists();
    }

    public static function isFamilyInvitation(Invitation $invitation): bool
    {
        return $invitation->kind === InvitationKind::FamilyMember && $invitation->device_id !== null;
    }
}
