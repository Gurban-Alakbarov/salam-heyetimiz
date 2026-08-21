<?php

use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Enums\RefreshTokenRevocationReason;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Users\Models\User;

/*
| POST /v1/auth/refresh — refreshToken (rotation + reuse detection, R-SEC-02).
*/

function authenticatedTokens(string $phone): array
{
    test()->postJson('/v1/auth/otp/request', ['phone' => $phone])->assertStatus(202);
    $code = app(FakeOtpTransport::class)->lastCodeFor($phone);

    return test()->postJson('/v1/auth/otp/verify', [
        'phone' => $phone,
        'code' => $code,
        'device' => ['install_uuid' => TEST_INSTALL_UUID, 'platform' => 'android'],
    ])->assertOk()->json();
}

it('rotates the refresh token and issues a fresh pair', function () {
    $tokens = authenticatedTokens('+994502220001');
    $old = $tokens['refresh_token'];

    $response = $this->postJson('/v1/auth/refresh', ['refresh_token' => $old]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'refresh_token', 'expires_in', 'user']);

    expect($response->json('refresh_token'))->not->toBe($old);

    // The presented token is now revoked with reason=rotated; exactly one active token remains.
    $user = User::query()->where('phone', '+994502220001')->firstOrFail();
    $rotated = RefreshToken::query()->where('token_hash', hash('sha256', $old))->firstOrFail();
    expect($rotated->revoked_at)->not->toBeNull()
        ->and($rotated->revocation_reason)->toBe(RefreshTokenRevocationReason::Rotated)
        ->and($rotated->replaced_by_id)->not->toBeNull()
        ->and(RefreshToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(1);
});

it('detects replay of a rotated token and revokes the whole family', function () {
    $tokens = authenticatedTokens('+994502220002');
    $r0 = $tokens['refresh_token'];

    $r1 = $this->postJson('/v1/auth/refresh', ['refresh_token' => $r0])->assertOk()->json('refresh_token');

    // Replaying the already-rotated R0 is a theft signal → 401 + family revoked.
    $this->postJson('/v1/auth/refresh', ['refresh_token' => $r0])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_refresh_token');

    // R1 (the legitimate successor) is now revoked too.
    $this->postJson('/v1/auth/refresh', ['refresh_token' => $r1])->assertStatus(401);

    $user = User::query()->where('phone', '+994502220002')->firstOrFail();
    expect(RefreshToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(0);
});

it('rejects an unknown refresh token', function () {
    $this->postJson('/v1/auth/refresh', ['refresh_token' => str_repeat('a', 43)])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_refresh_token');
});
