<?php

namespace App\Domain\Roster\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Support\InvitationTokens;
use App\Domain\Users\Models\User;
use App\Support\Time\Clock;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The single invitation engine (IMPLEMENTATION_PLAN B3 / §9) for both kinds — complex_resident (Komendant →
 * email) and family_member (head → email). Owns creation, resend (token rotation + rate limits), revoke,
 * live lookup and expiry. Acceptance/claim is NOT here (B6/B8). Plaintext tokens are returned exactly once
 * to the caller and travel only inside the (encrypted) email job; the table holds only token_hash.
 */
final class InvitationService
{
    public function __construct(
        private readonly InvitationTokens $tokens,
        private readonly Clock $clock,
    ) {}

    /** @return array{invitation: Invitation, token: string} */
    public function createComplexResident(int $complexId, AdminUser $inviter, string $email, string $firstName, string $lastName): array
    {
        return $this->create(InvitationKind::ComplexResident, [
            'complex_id' => $complexId,
            'invited_by_admin_id' => (int) $inviter->getKey(),
        ], 'admin:'.$inviter->getKey(), $email, $firstName, $lastName);
    }

    /**
     * @return array{invitation: Invitation, token: string} — `$deviceId` is the device the head grants on
     *         acceptance (B8, FamilyService); one pending invitation per (head, email).
     */
    public function createFamilyMember(User $head, string $email, string $firstName, string $lastName, ?int $deviceId = null): array
    {
        return $this->create(InvitationKind::FamilyMember, [
            'invited_by_user_id' => (int) $head->getKey(),
        ] + ($deviceId !== null ? ['device_id' => $deviceId] : []), 'user:'.$head->getKey(), $email, $firstName, $lastName);
    }

    /** Rotate the token, extend expiry, re-send. Allowed for pending or (time-)expired invitations only. */
    public function resend(Invitation $invitation): string
    {
        if (! in_array($invitation->status, [InvitationStatus::Pending, InvitationStatus::Expired], true)) {
            throw InvitationException::notResendable();
        }

        $now = $this->clock->now();
        $cooldown = (int) config('domain.invitations.resend_cooldown_seconds', 60);
        if ($invitation->last_sent_at !== null && $invitation->last_sent_at->greaterThan($now->subSeconds($cooldown))) {
            throw InvitationException::rateLimited($cooldown - (int) abs($now->getTimestamp() - $invitation->last_sent_at->getTimestamp()));
        }

        $this->hitLimit('invitation:send:inv:'.$invitation->getKey(), (int) config('domain.invitations.resend_max_per_day', 5));
        $this->hitLimit('invitation:send:inviter:'.$this->inviterKey($invitation), (int) config('domain.invitations.inviter_max_per_day', 30));

        $token = $this->tokens->generate();

        DB::transaction(function () use ($invitation, $token, $now): void {
            $invitation->forceFill([
                'token_hash' => $token['hash'],
                'status' => InvitationStatus::Pending->value,
                'expires_at' => $now->addDays($this->ttlDays()),
                'send_count' => (int) $invitation->send_count + 1,
                'last_sent_at' => $now,
            ])->save();
        });

        SendInvitationEmailJob::dispatch((int) $invitation->getKey(), $token['plaintext'])->afterCommit();

        return $token['plaintext'];
    }

    public function revoke(Invitation $invitation, ?User $byUser = null, ?AdminUser $byAdmin = null): Invitation
    {
        if ($invitation->status !== InvitationStatus::Pending) {
            throw InvitationException::notRevocable();
        }

        $invitation->forceFill([
            'status' => InvitationStatus::Cancelled->value,
            'revoked_at' => $this->clock->now(),
            'revoked_by_user_id' => $byUser?->getKey(),
            'revoked_by_admin_id' => $byAdmin?->getKey(),
        ])->save();

        return $invitation;
    }

    /**
     * The live invitation behind a plaintext token, or null. Unknown, expired, revoked, accepted and declined
     * all return null — callers answer with ONE uniform response (no enumeration — S-7).
     */
    public function findLive(string $plaintext): ?Invitation
    {
        if ($plaintext === '' || strlen($plaintext) > 128) {
            return null;
        }

        $invitation = Invitation::query()->where('token_hash', $this->tokens->hash($plaintext))->first();

        return $invitation !== null && $invitation->isLive($this->clock->now()) ? $invitation : null;
    }

    /** Hourly sweep: pending invitations past expiry → expired. Returns the count. */
    public function expireDue(): int
    {
        return Invitation::query()
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '<=', $this->clock->now())
            ->update(['status' => InvitationStatus::Expired->value, 'updated_at' => $this->clock->now()]);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array{invitation: Invitation, token: string}
     */
    private function create(InvitationKind $kind, array $target, string $inviterKey, string $email, string $firstName, string $lastName): array
    {
        $email = mb_strtolower(trim($email));
        $firstName = trim($firstName);
        $lastName = trim($lastName);
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $firstName === '' || $lastName === '') {
            throw InvitationException::invalidTarget();
        }

        $now = $this->clock->now();
        $scope = fn ($q) => $q->where('kind', $kind->value)->where('invitee_email', $email)->where(array_intersect_key($target, ['complex_id' => 1, 'invited_by_user_id' => 1]));

        // A stale pending row (past expiry, not yet swept) must not block a fresh invite.
        Invitation::query()->where($scope)->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '<=', $now)->update(['status' => InvitationStatus::Expired->value, 'updated_at' => $now]);

        if (Invitation::query()->where($scope)->where('status', InvitationStatus::Pending->value)->exists()) {
            throw InvitationException::alreadyPending();
        }

        $this->hitLimit('invitation:send:inviter:'.$inviterKey, (int) config('domain.invitations.inviter_max_per_day', 30));

        $token = $this->tokens->generate();

        try {
            /** @var Invitation $invitation */
            $invitation = DB::transaction(fn (): Invitation => Invitation::query()->create($target + [
                'kind' => $kind->value,
                'invitee_email' => $email,
                'invitee_first_name' => mb_substr($firstName, 0, 60),
                'invitee_last_name' => mb_substr($lastName, 0, 60),
                'token_hash' => $token['hash'],
                'status' => InvitationStatus::Pending->value,
                'expires_at' => $now->addDays($this->ttlDays()),
                'send_count' => 1,
                'last_sent_at' => $now,
            ]));
        } catch (QueryException $e) {
            // Concurrent duplicate lost the unique (kind, target, email, is_pending) race.
            if (str_contains($e->getMessage(), 'uq_invitations_active_')) {
                throw InvitationException::alreadyPending();
            }
            throw $e;
        }

        SendInvitationEmailJob::dispatch((int) $invitation->getKey(), $token['plaintext'])->afterCommit();

        return ['invitation' => $invitation, 'token' => $token['plaintext']];
    }

    private function hitLimit(string $key, int $max): void
    {
        if (RateLimiter::tooManyAttempts($key, $max)) {
            throw InvitationException::rateLimited(RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 86400);
    }

    private function inviterKey(Invitation $invitation): string
    {
        return $invitation->invited_by_admin_id !== null
            ? 'admin:'.$invitation->invited_by_admin_id
            : 'user:'.$invitation->invited_by_user_id;
    }

    private function ttlDays(): int
    {
        return max(1, (int) config('domain.invitations.ttl_days', 7));
    }
}
