<?php

namespace App\Domain\Auth\Support;

/** A freshly minted access token plus its TTL (seconds), for the AuthSuccess envelope. */
final readonly class IssuedAccessToken
{
    public function __construct(
        public string $token,
        public int $expiresIn,
    ) {}
}
