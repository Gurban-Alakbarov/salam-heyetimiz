<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Auth\Events\UserAuthenticated;
use App\Domain\Auth\Services\JwtService;
use App\Domain\Auth\Services\RefreshTokenService;
use App\Domain\Auth\Support\AuthTokens;

/**
 * Exchange a refresh token for a fresh access + refresh pair (openapi refreshToken). The
 * presented token is rotated (revoked + replaced); replay of a rotated token revokes the
 * whole install family (R-SEC-02). A new 15-minute access token is minted.
 */
final class RefreshTokens
{
    public function __construct(
        private readonly RefreshTokenService $refreshTokens,
        private readonly JwtService $jwt,
    ) {}

    public function handle(string $presentedToken, string $ip, ?string $userAgent): AuthTokens
    {
        $rotated = $this->refreshTokens->rotate($presentedToken, $ip, $userAgent);

        $access = $this->jwt->issueUserAccessToken(
            (int) $rotated['user']->getKey(),
            (string) $rotated['device']->install_uuid,
        );

        UserAuthenticated::dispatch($rotated['user'], $rotated['device'], 'refresh', $ip);

        return new AuthTokens(
            accessToken: $access->token,
            expiresIn: $access->expiresIn,
            refreshToken: $rotated['plaintext'],
            refreshExpiresIn: (int) config('domain.auth.refresh.ttl_days') * 86400,
            user: $rotated['user'],
        );
    }
}
