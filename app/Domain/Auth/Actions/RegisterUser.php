<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Events\EmailOtpRequested;
use App\Domain\Auth\Exceptions\RegistrationException;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Users\Enums\AccountType;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Domain\Users\Queries\AccountAccessFootprint;

/**
 * Start (or resume) a registration: create the account only if neither identifier is already verified,
 * reuse an existing UNVERIFIED row (so re-registering with the same email does not duplicate — brief §4),
 * reject an already-verified email (§5) or a phone taken by someone else. Then dispatch the email OTP.
 * No Device / Resident / Subscription / Complex is touched (domain invariant — ARCHITECTURE §6).
 */
final class RegisterUser
{
    public function __construct(
        private readonly OtpService $otp,
        private readonly AccountAccessFootprint $footprint,
        private readonly AuditLogger $audit,
    ) {}

    public function handle(string $firstName, string $lastName, string $phone, string $email, string $ip, string $locale, ?AccountType $accountType = null): array
    {
        $email = mb_strtolower(trim($email));
        $byEmail = User::query()->where('email', $email)->first();
        $byPhone = User::query()->where('phone', $phone)->first();

        // §5 — an already-verified email cannot register again.
        if ($byEmail !== null && $byEmail->email_verified_at !== null) {
            throw RegistrationException::alreadyRegistered();
        }
        // The phone belongs to a different, already-verified account.
        if ($byPhone !== null && $byPhone->email_verified_at !== null
            && ($byEmail === null || $byPhone->getKey() !== $byEmail->getKey())) {
            throw RegistrationException::phoneTaken();
        }
        // Email on one unverified row, phone on a different one — cannot merge two identities.
        if ($byEmail !== null && $byPhone !== null && $byEmail->getKey() !== $byPhone->getKey()) {
            throw RegistrationException::conflict();
        }

        $fullName = trim($firstName.' '.$lastName);
        $target = $byEmail ?? $byPhone; // reuse the matching unverified row, if any

        // K1 — a phone match alone proves nothing. An unverified account with LIVE access (device owner, roster,
        // complex, family, Komendant link) is continued only with the email recorded on it (by an admin) and its
        // own phone — the email OTP then proves it. Otherwise stop here, before anything on that row changes.
        $live = $target !== null && $this->footprint->isLive((int) $target->getKey());
        if ($live && $byEmail === null) {
            $this->audit->record('registration.claim_blocked', [
                'user_id' => (int) $target->getKey(), 'email' => EmailMask::mask($email),
            ], User::class, (int) $target->getKey());
            throw RegistrationException::phoneLinkedToAccount();
        }
        if ($live && $target->phone !== $phone) {
            throw RegistrationException::conflict();
        }

        if ($target !== null) {
            // §4 — reuse the unverified account; refresh the profile (also upgrades an old phone-only row).
            // A live account keeps its recorded name until the owner has proven the email (fills only a blank one).
            $name = $live && filled($target->full_name) ? $target->full_name : $fullName;
            $target->forceFill(['full_name' => $name, 'phone' => $phone, 'email' => $email])->save();
            // B9: an unverified account may still pick its type; an already-typed one is never re-typed here.
            if ($accountType !== null) {
                User::query()->whereKey($target->getKey())->whereNull('account_type')->update(['account_type' => $accountType->value]);
            }
            $user = $target;
        } else {
            $user = User::query()->create([
                'full_name' => $fullName,
                'phone' => $phone,
                'phone_country' => 'AZ',
                'email' => $email,
                'preferred_language' => 'az',
                'status' => UserStatus::Active->value,
                'account_type' => $accountType?->value, // B9 — NULL keeps the legacy behaviour
            ]);
        }

        $timing = $this->otp->issueToEmail($email, OtpPurpose::EmailVerify, $ip, $locale);
        EmailOtpRequested::dispatch(EmailMask::mask($email), OtpPurpose::EmailVerify->value);

        return $timing;
    }
}
