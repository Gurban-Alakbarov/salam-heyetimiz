<?php

namespace App\Domain\Auth\Support;

/**
 * Outcome of verifying an email OTP. `wasRegistration` is true when this verification flipped
 * email_verified_at from null (i.e. it completed a registration) vs. an ordinary email login — the
 * controller uses it to pick the response message.
 */
final readonly class VerifyResult
{
    public function __construct(
        public AuthTokens $tokens,
        public bool $wasRegistration,
    ) {}
}
