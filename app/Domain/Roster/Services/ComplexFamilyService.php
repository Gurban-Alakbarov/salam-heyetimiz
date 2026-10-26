<?php

namespace App\Domain\Roster\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Events\ComplexFamilyChanged;
use App\Domain\Roster\Exceptions\ComplexFamilyException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Subscriptions\Actions\CancelOnAccessRemoval;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * O7 — the Komendant manages the FAMILIES in its complex (decided 2026-10-27). Only the complex's complex-mode
 * devices are touched; a family's rows on private devices belong to that device's owner and stay as they are.
 *
 *  - removeMember: a family member leaves the complex — their family rows there are revoked, the subscriptions
 *    cancelled (no refund, BR-18), and a family link left without any live row ends.
 *  - changeHead: a member takes the family over at once (no consent step): their rows become their own access,
 *    the other members' rows move to links under them, nobody pays again (every subscription is 12 AZN / 30
 *    days, BR-21 — only the tier label follows the role). The old head either stays as a member of the new
 *    head's family (default) or leaves the complex.
 *  - endLinksWithoutRows: after a head is removed with the whole family, no link stays "active" without access.
 * Every change is audited and fires ComplexFamilyChanged after commit (the Notifications module tells people).
 */
final class ComplexFamilyService
{
    public function __construct(
        private readonly RosterService $roster,
        private readonly ComplexMembershipService $memberships,
        private readonly InvitationService $invitations,
        private readonly CancelOnAccessRemoval $cancelOnRemoval,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /** @return Collection<int, int> the complex's complex-mode device ids */
    public function deviceIds(int $complexId): Collection
    {
        return Device::query()->where('complex_id', $complexId)
            ->where('ownership_mode', DeviceOwnershipMode::Complex->value)
            ->pluck('id')->map(fn ($id): int => (int) $id);
    }

    /** @return Collection<int, DeviceUser> live family rows granted by $headUserId on the complex's devices */
    public function familyRows(int $complexId, int $headUserId): Collection
    {
        $linkIds = FamilyLink::query()->where('head_user_id', $headUserId)
            ->where('status', FamilyLinkStatus::Active->value)->pluck('id');
        if ($linkIds->isEmpty()) {
            return collect();
        }

        return DeviceUser::query()->with(['user', 'device'])
            ->whereIn('device_id', $this->deviceIds($complexId))
            ->where('status', DeviceUserStatus::Active->value)
            ->whereIn('family_link_id', $linkIds)
            ->orderBy('id')
            ->get();
    }

    /**
     * Every family on the complex's devices, for the residents lists: head user id → its members, each with how
     * many live subscriptions they hold on the complex's devices.
     *
     * @return array<int, list<array{user: User, active_subscriptions: int}>>
     */
    public function familiesByHead(int $complexId): array
    {
        $rows = DeviceUser::query()->with(['user', 'familyLink'])
            ->whereIn('device_id', $this->deviceIds($complexId))
            ->where('status', DeviceUserStatus::Active->value)
            ->whereNotNull('family_link_id')
            ->get()
            ->filter(fn (DeviceUser $r): bool => $r->familyLink?->status === FamilyLinkStatus::Active && $r->user !== null);

        $activeSubs = Subscription::query()
            ->whereIn('device_user_id', $rows->pluck('id'))
            ->where('status', SubscriptionStatus::Active->value)
            ->where('ends_at', '>', $this->clock->now())
            ->pluck('device_user_id')->map(fn ($id): int => (int) $id)->all();

        $families = [];
        foreach ($rows->groupBy(fn (DeviceUser $r): int => (int) $r->familyLink->head_user_id) as $headId => $headRows) {
            foreach ($headRows->groupBy('user_id') as $memberRows) {
                $families[(int) $headId][] = [
                    'user' => $memberRows->first()->user,
                    'active_subscriptions' => $memberRows->filter(fn (DeviceUser $r): bool => in_array((int) $r->id, $activeSubs, true))->count(),
                ];
            }
        }

        return $families;
    }

    /** @return Collection<int, User> the members of $headUserId's family on this complex's devices */
    public function members(int $complexId, int $headUserId): Collection
    {
        return $this->familyRows($complexId, $headUserId)->pluck('user')->filter()->unique('id')->values();
    }

    /** @return Collection<int, DeviceUser> $userId's own (non-family) live rows on the complex's devices */
    private function ownRows(int $complexId, int $userId): Collection
    {
        return DeviceUser::query()->with(['user', 'device'])
            ->whereIn('device_id', $this->deviceIds($complexId))
            ->where('user_id', $userId)
            ->where('status', DeviceUserStatus::Active->value)
            ->whereNull('family_link_id')
            ->get();
    }

    /** @return array{revoked_rows: int, cancelled_subscriptions: int}|null  null when $member has no family rows here */
    public function removeMember(int $complexId, User $member, AdminUser $manager, ?User $byUser): ?array
    {
        $rows = DeviceUser::query()->with(['device', 'familyLink'])
            ->whereIn('device_id', $this->deviceIds($complexId))
            ->where('user_id', $member->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->whereNotNull('family_link_id')
            ->get();
        if ($rows->isEmpty()) {
            return null;
        }

        $headIds = $rows->pluck('familyLink.head_user_id')->filter()->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $context = $this->context($complexId, $manager, $byUser) + ['member_user_id' => (int) $member->getKey(), 'head_user_ids' => $headIds];

        $result = DB::transaction(function () use ($rows, $member, $manager, $context): array {
            $cancelled = 0;
            foreach ($rows as $row) {
                if ($this->cancelOnRemoval->handle($row, 'removed_by_komendant', $context) !== null) {
                    $cancelled++;
                }
                $this->roster->removeMember($row->device, $member, ActorKind::Admin, (int) $manager->getKey());
            }
            $this->endLinksWithoutRows($rows->pluck('family_link_id')->unique()->all(), $manager);

            $this->audit->record('complex.family_member_removed', $context + [
                'revoked_rows' => $rows->count(),
                'cancelled_subscriptions' => $cancelled,
            ], User::class, (int) $member->getKey());

            return ['revoked_rows' => $rows->count(), 'cancelled_subscriptions' => $cancelled];
        });

        foreach ($headIds as $headId) {
            ComplexFamilyChanged::dispatch(ComplexFamilyChanged::MEMBER_REMOVED, $complexId, (int) $member->getKey(), $headId);
        }

        return $result;
    }

    /**
     * @return array{new_head_user_id: int, members_moved: int, old_head: string, revoked_rows: int, cancelled_subscriptions: int}
     */
    public function changeHead(int $complexId, User $head, User $newHead, bool $removeOldHead, AdminUser $manager, ?User $byUser): array
    {
        $headId = (int) $head->getKey();
        $newHeadId = (int) $newHead->getKey();

        $headRows = $this->ownRows($complexId, $headId);
        if ($headRows->isEmpty()) {
            throw ComplexFamilyException::notHead();
        }
        $familyRows = $this->familyRows($complexId, $headId);
        $newHeadRows = $familyRows->where('user_id', $newHeadId);
        if ($newHeadRows->isEmpty()) {
            throw ComplexFamilyException::notMember();
        }
        $otherRows = $familyRows->where('user_id', '!=', $newHeadId);
        $otherMemberIds = $otherRows->pluck('user_id')->unique()->map(fn ($id): int => (int) $id)->values()->all();
        $context = $this->context($complexId, $manager, $byUser) + [
            'old_head_user_id' => $headId,
            'new_head_user_id' => $newHeadId,
            'old_head' => $removeOldHead ? 'removed' : 'member',
        ];

        $cancelled = 0;
        DB::transaction(function () use ($complexId, $head, $newHead, $headRows, $familyRows, $newHeadRows, $otherRows, $removeOldHead, $manager, $byUser, $context, &$cancelled): void {
            $oldLinkIds = $familyRows->pluck('family_link_id')->unique()->all();

            // The new head's rows become their own access (the role follows: main tier; same price, BR-21).
            foreach ($newHeadRows as $row) {
                $row->forceFill(['family_link_id' => null, 'added_by_user_id' => $newHead->getKey()])->save();
                $this->setTier($row, SubscriptionTier::Main);
            }

            // The other members move under the new head; their access and paid days stay exactly as they are.
            foreach ($otherRows as $row) {
                $row->forceFill([
                    'family_link_id' => $this->linkFor($newHead, $row->user)->getKey(),
                    'added_by_user_id' => $newHead->getKey(),
                ])->save();
            }

            if ($removeOldHead) {
                foreach ($headRows as $row) {
                    if ($this->cancelOnRemoval->handle($row, 'removed_by_komendant', $context) !== null) {
                        $cancelled++;
                    }
                    $this->roster->removeMember($row->device, $head, ActorKind::Admin, (int) $manager->getKey());
                }
                $this->memberships->remove($complexId, $head, $byUser, $manager);
            } else {
                $link = $this->linkFor($newHead, $head);
                foreach ($headRows as $row) {
                    $row->forceFill(['family_link_id' => $link->getKey(), 'added_by_user_id' => $newHead->getKey()])->save();
                    $this->setTier($row, SubscriptionTier::Additional);
                }
            }

            // A family member joined through the family, not the complex: as head they are a resident now.
            if (! $this->memberships->isMember($complexId, (int) $newHead->getKey())) {
                $this->memberships->join($complexId, $newHead);
            }

            // The old head's invitations into this complex's gates can no longer be accepted into their family.
            Invitation::query()
                ->where('invited_by_user_id', $head->getKey())
                ->where('kind', InvitationKind::FamilyMember->value)
                ->where('status', InvitationStatus::Pending->value)
                ->whereIn('device_id', $this->deviceIds($complexId))
                ->get()
                ->each(fn (Invitation $invitation) => $this->invitations->revoke($invitation, $byUser, $manager));

            $this->endLinksWithoutRows($oldLinkIds, $manager);

            $this->audit->record('complex.family_head_changed', $context + [
                'members_moved' => $otherRows->pluck('user_id')->unique()->count(),
            ], User::class, (int) $newHead->getKey());
        });

        ComplexFamilyChanged::dispatch(
            ComplexFamilyChanged::HEAD_CHANGED, $complexId, $newHeadId, $newHeadId, $otherMemberIds, $headId, $removeOldHead,
        );

        return [
            'new_head_user_id' => $newHeadId,
            'members_moved' => count($otherMemberIds),
            'old_head' => $removeOldHead ? 'removed' : 'member',
            'revoked_rows' => $removeOldHead ? $headRows->count() : 0,
            'cancelled_subscriptions' => $cancelled,
        ];
    }

    /** End the given family links that no longer grant any live row (removed by the manager). */
    public function endLinksWithoutRows(array $linkIds, AdminUser $manager): void
    {
        foreach (FamilyLink::query()->whereKey($linkIds)->where('status', FamilyLinkStatus::Active->value)->get() as $link) {
            $live = DeviceUser::query()->where('family_link_id', $link->getKey())
                ->where('status', DeviceUserStatus::Active->value)->exists();
            if (! $live) {
                $link->forceFill([
                    'status' => FamilyLinkStatus::Removed->value,
                    'removed_at' => $this->clock->now(),
                    'removed_by_admin_id' => $manager->getKey(),
                ])->save();
            }
        }
    }

    /** The active head → member link, created when the two are not linked yet. */
    private function linkFor(User $head, User $member): FamilyLink
    {
        return FamilyLink::query()
            ->where('head_user_id', $head->getKey())
            ->where('member_user_id', $member->getKey())
            ->where('status', FamilyLinkStatus::Active->value)
            ->first()
            ?? FamilyLink::query()->create([
                'head_user_id' => $head->getKey(),
                'member_user_id' => $member->getKey(),
                'status' => FamilyLinkStatus::Active->value,
                'linked_at' => $this->clock->now(),
            ]);
    }

    private function setTier(DeviceUser $row, SubscriptionTier $tier): void
    {
        Subscription::query()->where('device_user_id', $row->getKey())->update(['tier' => $tier->value]);
    }

    /** @return array<string, mixed> */
    private function context(int $complexId, AdminUser $manager, ?User $byUser): array
    {
        return [
            'complex_id' => $complexId,
            'by_admin_id' => (int) $manager->getKey(),
            'by_user_id' => $byUser !== null ? (int) $byUser->getKey() : null,
        ];
    }
}
