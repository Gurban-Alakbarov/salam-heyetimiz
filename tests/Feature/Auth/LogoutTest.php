<?php

use App\Domain\Auth\Enums\RefreshTokenRevocationReason;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Users\Models\User;

/*
| POST /v1/auth/logout — logout (204; revokes the current install's refresh token).
*/

it('revokes the current install refresh token', function () {
    $phone = '+994503330001';
    $tokens = authenticateViaOtp($phone);

    $this->withHeaders(bearer($tokens['access_token']))
        ->postJson('/v1/auth/logout')
        ->assertStatus(204);

    $user = User::query()->where('phone', $phone)->firstOrFail();
    $token = RefreshToken::query()->where('user_id', $user->id)->latest('id')->firstOrFail();

    expect($token->revoked_at)->not->toBeNull()
        ->and($token->revocation_reason)->toBe(RefreshTokenRevocationReason::Logout);
});

it('requires authentication', function () {
    $this->postJson('/v1/auth/logout')->assertStatus(401);
});
