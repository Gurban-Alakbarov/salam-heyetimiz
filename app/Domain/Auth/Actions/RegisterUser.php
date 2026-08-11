<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Events\EmailOtpRequested;
use App\Domain\Auth\Exceptions\RegistrationException;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Auth\Support\EmailMask;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;

/**
 * Start (or resume) a registration: create the account only if neither identifier is already verified,
 * reuse an existing UNVERIFIED row (so re-registering with the same email does not duplicate — brief §4),
 * reject an already-verified email (§5) or a phone taken by someone else. Then dispatch the email OTP.
 * No Device / Resident / Subscription / Complex is touched (domain invariant — ARCHITECTURE §6).
 */
final class RegisterUser
{
    public function __construct(private readonly OtpService $otp) {}

    public function handle(string $firstName, string $lastName, string $phone, string $email, string $ip, string $locale): array
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

        if ($target !== null) {
            // §4 — reuse the unverified account; refresh the profile (also upgrades an old phone-only row).
            $target->forceFill(['full_name' => $fullName, 'phone' => $phone, 'email' => $email])->save();
            $user = $target;
        } else {
            $user = User::query()->create([
                'full_name' => $fullName,
                'phone' => $phone,
                'phone_country' => 'AZ',
                'email' => $email,
                'preferred_language' => 'az',
                'status' => UserStatus::Active->value,
            ]);
        }

        $timing = $this->otp->issueToEmail($email, OtpPurpose::EmailVerify, $ip, $locale);
        EmailOtpRequested::dispatch(EmailMask::mask($email), OtpPurpose::EmailVerify->value);

        return $timing;
    }
}
