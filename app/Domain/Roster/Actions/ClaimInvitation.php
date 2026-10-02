<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\Complex;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\FamilyService;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Accept / decline an invitation (IMPLEMENTATION_PLAN §9 / B6). Only the invitee may act: the caller's
 * VERIFIED email must equal `invitee_email` (case-insensitive). The status change is a CONDITIONAL update
 * (`WHERE status='pending' AND expires_at>now`) so two concurrent claims can never both win (S-11).
 * `complex_resident` → active `complex_members` row. `family_member` (B8) → active family link + the
 * head-granted device row + the member's own pending subscription (FamilyService); a family invitation
 * without a device (pre-B8 service-only rows) stays unsupported and is never touched.
 */
final class ClaimInvitation
{
    public function __construct(
        private readonly ComplexMembershipService $memberships,
        private readonly FamilyService $family,
        private readonly AuditLogger $audit,
        private readonly Clock $clock,
    ) {}

    /** Pre-flight shared by register (before an account is verified) and accept/claim. */
    public function assertClaimableBy(Invitation $invitation, string $email): void
    {
        if (! $invitation->isLive($this->clock->now())) {
            throw InvitationException::notClaimable();
        }
        $isFamily = FamilyService::isFamilyInvitation($invitation);
        if ($invitation->kind !== InvitationKind::ComplexResident && ! $isFamily) {
            throw InvitationException::kindUnsupported();
        }
        if (mb_strtolower(trim($email)) !== mb_strtolower((string) $invitation->invitee_email)) {
            throw InvitationException::emailMismatch();
        }
        if ($isFamily) {
            $this->family->assertAcceptable($invitation);

            return;
        }
        if ($invitation->complex_id === null || ! Complex::query()->whereKey($invitation->complex_id)->exists()) {
            throw InvitationException::notClaimable();
        }
    }

    /** @return array<string, mixed> complex: {kind, complex}; family: {kind, family_link_id, device, subscription_id} */
    public function handle(Invitation $invitation, User $user): array
    {
        $this->assertInvitee($invitation, $user);

        return DB::transaction(function () use ($invitation, $user): array {
            $now = $this->clock->now();
            $won = Invitation::query()->whereKey($invitation->getKey())
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', $now)
                ->update([
                    'status' => InvitationStatus::Accepted->value,
                    'accepted_at' => $now,
                    'invitee_user_id' => $user->getKey(),
                    'updated_at' => $now,
                ]);
            if ($won !== 1) {
                throw InvitationException::notClaimable();
            }

            if (FamilyService::isFamilyInvitation($invitation)) {
                $result = ['kind' => $invitation->kind->value] + $this->family->accept($invitation, $user);
                $this->audit->record('invitation.accepted', [
                    'invitation_id' => (int) $invitation->getKey(),
                    'kind' => $invitation->kind->value,
                    'device_id' => (int) $invitation->device_id,
                    'head_user_id' => (int) $invitation->invited_by_user_id,
                    'user_id' => (int) $user->getKey(),
                ], Invitation::class, (int) $invitation->getKey());

                return $result;
            }

            $complexId = (int) $invitation->complex_id;
            $this->memberships->join($complexId, $user, (int) $invitation->getKey());

            $this->audit->record('invitation.accepted', [
                'invitation_id' => (int) $invitation->getKey(),
                'kind' => $invitation->kind->value,
                'complex_id' => $complexId,
                'user_id' => (int) $user->getKey(),
            ], Invitation::class, (int) $invitation->getKey());

            return [
                'kind' => $invitation->kind->value,
                'complex' => ['id' => $complexId, 'name' => Complex::query()->whereKey($complexId)->value('name')],
            ];
        });
    }

    public function decline(Invitation $invitation, User $user): void
    {
        $this->assertInvitee($invitation, $user);

        $now = $this->clock->now();
        $won = Invitation::query()->whereKey($invitation->getKey())
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', $now)
            ->update(['status' => InvitationStatus::Declined->value, 'invitee_user_id' => $user->getKey(), 'updated_at' => $now]);
        if ($won !== 1) {
            throw InvitationException::notClaimable();
        }

        $this->audit->record('invitation.declined', [
            'invitation_id' => (int) $invitation->getKey(),
            'kind' => $invitation->kind->value,
            'user_id' => (int) $user->getKey(),
        ], Invitation::class, (int) $invitation->getKey());
    }

    private function assertInvitee(Invitation $invitation, User $user): void
    {
        if ($user->email_verified_at === null || $user->email === null) {
            throw InvitationException::emailMismatch();
        }
        $this->assertClaimableBy($invitation, (string) $user->email);
    }
}
