<?php

namespace App\Domain\Auth\Support;

use App\Domain\Users\Models\User;

/** Assembled mobile auth result for the AuthSuccess envelope (access + opaque refresh + user). */
final readonly class AuthTokens
{
    public function __construct(
        public string $accessToken,
        public int $expiresIn,
        public string $refreshToken,
        public int $refreshExpiresIn,
        public User $user,
    ) {}
}
