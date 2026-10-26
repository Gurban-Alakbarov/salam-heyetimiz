<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Exceptions\ComplexFamilyException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexFamilyService;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Actions\CancelOnAccessRemoval;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\DB;

/**
 * Komendant removes a resident from their complex (IMPLEMENTATION_PLAN §12 / B5, BR-11, BR-18) — from the mobile
 * app (by its linked user) or from the admin panel (O10: a complex_manager's "remove" there is this same action,
 * never an account deletion; $byUser is then the manager's linked mobile account, if any). In ONE
 * transaction: membership → removed; on every complex-mode device of THAT complex, the resident's own roster
 * row and the rows they granted to family members are revoked (whitelist sync follows via the existing
 * RosterUserRemoved event); each live subscription on those rows is cancelled with no refund; audited.
 * Private devices — even inside the complex — are never touched.
 *
 * O7 — families: a family member (who joined through the family, not the complex) is removed from the complex's
 * family rows (ComplexFamilyService::removeMember). A resident who HEADS a family here needs a decision —
 * `new_head` (that member takes the family over, then the resident leaves) or `remove_family` (the family leaves
 * with them and its links end); without one → 409 family_decision_required listing the members.
 *
 * @return array<string, mixed>|null  null when the user is neither a resident nor a family member here
 */
final class RemoveComplexResident
{
    public function __construct(
        private readonly ComplexMembershipService $memberships,
        private readonly RosterService $roster,
        private readonly CancelOnAccessRemoval $cancelOnRemoval,
        private readonly AuditLogger $audit,
        private readonly ComplexFamilyService $family,
    ) {}

    public const FAMILY_NEW_HEAD = 'new_head';

    public const FAMILY_REMOVE = 'remove_family';

    public function handle(int $complexId, User $resident, ?User $byUser, AdminUser $byManager, ?string $familyAction = null, ?int $newHeadUserId = null): ?array
    {
        $residentId = (int) $resident->getKey();
        if (! $this->memberships->isMember($complexId, $residentId)) {
            $removed = $this->family->removeMember($complexId, $resident, $byManager, $byUser);

            return $removed === null ? null : $removed + ['removed_as' => 'family_member'];
        }

        $members = $this->family->members($complexId, $residentId);
        if ($members->isNotEmpty()) {
            if ($familyAction === null) {
                throw ComplexFamilyException::decisionRequired($members->map(fn (User $m): array => [
                    'user_id' => (int) $m->getKey(), 'full_name' => $m->full_name, 'email' => $m->email,
                ])->values()->all());
            }
            if ($familyAction === self::FAMILY_NEW_HEAD) {
                $newHead = $members->first(fn (User $m): bool => (int) $m->getKey() === (int) $newHeadUserId)
                    ?? throw ComplexFamilyException::notMember();

                return $this->family->changeHead($complexId, $resident, $newHead, true, $byManager, $byUser) + ['removed_as' => 'resident'];
            }
            if ($familyAction !== self::FAMILY_REMOVE) {
                throw ComplexFamilyException::unknownAction();
            }
        }

        return DB::transaction(function () use ($complexId, $resident, $byUser, $byManager): array {
            $this->memberships->remove($complexId, $resident, $byUser, $byManager);

            $deviceIds = Device::query()
                ->where('complex_id', $complexId)
                ->where('ownership_mode', DeviceOwnershipMode::Complex->value)
                ->pluck('id');

            $rows = DeviceUser::query()
                ->whereIn('device_id', $deviceIds)
                ->where('status', DeviceUserStatus::Active->value)
                ->where(fn ($q) => $q
                    ->where('user_id', $resident->getKey())
                    // rows this resident granted to their family (family-link rows they added)
                    ->orWhere(fn ($f) => $f->whereNotNull('family_link_id')->where('added_by_user_id', $resident->getKey())))
                ->with(['device', 'user'])
                ->get();

            $audit = ['complex_id' => $complexId, 'removed_user_id' => (int) $resident->getKey(), 'by_user_id' => $byUser !== null ? (int) $byUser->getKey() : null, 'by_admin_id' => (int) $byManager->getKey()];
            $cancelled = 0;

            foreach ($rows as $row) {
                if ($this->cancelOnRemoval->handle($row, 'removed_by_komendant', $audit) !== null) {
                    $cancelled++;
                }
                $this->roster->removeMember($row->device, $row->user, ActorKind::Admin, (int) $byManager->getKey());
            }

            // O7 — no family link stays "active" without access: the ones this removal emptied end here.
            $linkIds = $rows->pluck('family_link_id')->filter()->unique()->all();
            $this->family->endLinksWithoutRows($linkIds, $byManager);

            $this->audit->record('complex.resident_removed', $audit + [
                'revoked_rows' => $rows->count(),
                'cancelled_subscriptions' => $cancelled,
                'family_links_checked' => array_values($linkIds),
            ], User::class, (int) $resident->getKey());

            return ['revoked_rows' => $rows->count(), 'cancelled_subscriptions' => $cancelled, 'removed_as' => 'resident'];
        });
    }
}
