<?php

namespace App\Domain\Roster\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Roster\Enums\ComplexMemberStatus;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Residential-complex membership (IMPLEMENTATION_PLAN B4). Membership only — it never touches device access or
 * subscriptions: revoking a removed resident's roster rows and cancelling their subscriptions is the
 * Komendant removal flow (B5 — RemoveComplexResident / CancelOnAccessRemoval).
 */
final class ComplexMembershipService
{
    public function __construct(private readonly Clock $clock) {}

    /** Join (or re-join) a complex. Idempotent — an already-active membership is returned unchanged. */
    public function join(int $complexId, User $user, ?int $invitationId = null): ComplexMember
    {
        return DB::transaction(function () use ($complexId, $user, $invitationId): ComplexMember {
            $active = $this->activeMembership($complexId, (int) $user->getKey(), lock: true);
            if ($active !== null) {
                return $active;
            }

            /** @var ComplexMember $member */
            $member = ComplexMember::query()->create([
                'complex_id' => $complexId,
                'user_id' => $user->getKey(),
                'role' => 'resident',
                'status' => ComplexMemberStatus::Active->value,
                'invitation_id' => $invitationId,
                'joined_at' => $this->clock->now(),
            ]);

            return $member;
        });
    }

    /** End a membership (history kept: the row becomes `removed`). Returns false when there was none. */
    public function remove(int $complexId, User $user, ?User $byUser = null, ?AdminUser $byAdmin = null): bool
    {
        $member = $this->activeMembership($complexId, (int) $user->getKey());
        if ($member === null) {
            return false;
        }

        $member->forceFill([
            'status' => ComplexMemberStatus::Removed->value,
            'removed_at' => $this->clock->now(),
            'removed_by_user_id' => $byUser?->getKey(),
            'removed_by_admin_id' => $byAdmin?->getKey(),
        ])->save();

        return true;
    }

    public function isMember(int $complexId, int $userId): bool
    {
        return $this->activeMembership($complexId, $userId) !== null;
    }

    /** @return array<int, int> complex ids the user is an active resident of */
    public function complexIdsFor(int $userId): array
    {
        return ComplexMember::query()
            ->where('user_id', $userId)
            ->where('status', ComplexMemberStatus::Active->value)
            ->pluck('complex_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
    }

    private function activeMembership(int $complexId, int $userId, bool $lock = false): ?ComplexMember
    {
        $query = ComplexMember::query()
            ->where('complex_id', $complexId)
            ->where('user_id', $userId)
            ->where('status', ComplexMemberStatus::Active->value);

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }
}
