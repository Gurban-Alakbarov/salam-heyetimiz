<?php

namespace App\Domain\Users\Services;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Users\Exceptions\AccountEmailException;
use App\Domain\Users\Models\User;

/**
 * K1 — the email an admin records on a not-yet-verified account (e.g. an owner created by phone at device
 * assignment). Registration lets a phone-matched account with live access be continued ONLY with this
 * email, and the email OTP then proves the registrant owns it; a phone number alone proves nothing.
 * The email stays unverified here — verification only ever happens through the OTP.
 */
final class AccountEmailService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Record $email on an unverified account. A verified account keeps its own email: re-sending that same
     * email is a no-op, a different one is refused (409 email_already_verified).
     */
    public function record(User $user, string $email): User
    {
        $email = mb_strtolower(trim($email));

        if ($user->email_verified_at !== null) {
            if ($user->email === $email) {
                return $user;
            }
            throw AccountEmailException::alreadyVerified();
        }
        if ($user->email === $email) {
            return $user;
        }

        $taken = User::withTrashed()->where('email', $email)->whereKeyNot($user->getKey())->exists();
        if ($taken) {
            throw AccountEmailException::taken();
        }

        $previous = $user->email;
        $user->forceFill(['email' => $email])->save();

        $this->audit->record('resident.email_recorded', [
            'user_id' => (int) $user->getKey(),
            'from' => $previous !== null ? EmailMask::mask($previous) : null,
            'to' => EmailMask::mask($email),
        ], User::class, (int) $user->getKey());

        return $user;
    }

    /**
     * The assignment flows (assign owner / transfer / add member): an optional email is recorded on an
     * unverified account; on an already-verified account it is ignored — that owner has already claimed it.
     */
    public function recordIfUnverified(User $user, ?string $email): User
    {
        if ($email === null || trim($email) === '' || $user->email_verified_at !== null) {
            return $user;
        }

        return $this->record($user, $email);
    }
}
