<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Actions\CancelOnAccessRemoval;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\DB;

/**
 * Komendant removes a resident from their complex (IMPLEMENTATION_PLAN §12 / B5, BR-11, BR-18). In ONE
 * transaction: membership → removed; on every complex-mode device of THAT complex, the resident's own roster
 * row and the rows they granted to family members are revoked (whitelist sync follows via the existing
 * RosterUserRemoved event); each live subscription on those rows is cancelled with no refund; audited.
 * Private devices — even inside the complex — are never touched.
 *
 * @return array{revoked_rows:int, cancelled_subscriptions:int}|null  null when the user is not a resident here
 */
final class RemoveComplexResident
{
    public function __construct(
        private readonly ComplexMembershipService $memberships,
        private readonly RosterService $roster,
        private readonly CancelOnAccessRemoval $cancelOnRemoval,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(int $complexId, User $resident, User $byUser, AdminUser $byManager): ?array
    {
        if (! $this->memberships->isMember($complexId, (int) $resident->getKey())) {
            return null;
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

            $audit = ['complex_id' => $complexId, 'removed_user_id' => (int) $resident->getKey(), 'by_user_id' => (int) $byUser->getKey(), 'by_admin_id' => (int) $byManager->getKey()];
            $cancelled = 0;

            foreach ($rows as $row) {
                if ($this->cancelOnRemoval->handle($row, 'removed_by_komendant', $audit) !== null) {
                    $cancelled++;
                }
                $this->roster->removeMember($row->device, $row->user, ActorKind::Admin, (int) $byManager->getKey());
            }

            $this->audit->record('complex.resident_removed', $audit + [
                'revoked_rows' => $rows->count(),
                'cancelled_subscriptions' => $cancelled,
            ], User::class, (int) $resident->getKey());

            return ['revoked_rows' => $rows->count(), 'cancelled_subscriptions' => $cancelled];
        });
    }
}
