<?php

use App\Domain\Admin\Services\SettingsService;

/*
| POST /admin/v1/auth/2fa/verify — adminVerify2fa (step 2: TOTP or single-use recovery code).
| The 2FA path requires the global Require-2FA toggle ON (default is OFF).
*/

beforeEach(fn () => app(SettingsService::class)->set(SettingsService::REQUIRE_2FA, '1'));

function adminChallenge(string $email): string
{
    return test()->postJson('/admin/v1/auth/login', [
        'email' => $email,
        'password' => 'password-12chars',
    ])->assertOk()->json('challenge_token');
}

it('issues an admin token for a valid TOTP', function () {
    $admin = makeAdminWith2fa('2fa-ok@salamhayetimiz.az');
    $challenge = adminChallenge('2fa-ok@salamhayetimiz.az');

    $response = $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => $challenge,
        'totp' => currentTotp(),
    ]);

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 1800)
        ->assertJsonPath('used_recovery_code', false)
        ->assertJsonPath('recovery_codes_remaining', 8)
        ->assertJsonPath('admin.email', '2fa-ok@salamhayetimiz.az')
        ->assertJsonStructure(['access_token', 'admin' => ['id', 'role', 'is_2fa_enabled']]);

    expect($admin->fresh()->last_login_at)->not->toBeNull();
});

it('rejects a wrong TOTP', function () {
    makeAdminWith2fa('2fa-bad@salamhayetimiz.az');
    $challenge = adminChallenge('2fa-bad@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => $challenge,
        'totp' => '000000',
    ])->assertStatus(401)->assertJsonPath('error.code', 'wrong_totp');
});

it('accepts a single-use recovery code and decrements the remaining count', function () {
    makeAdminWith2fa('2fa-rec@salamhayetimiz.az');
    $challenge = adminChallenge('2fa-rec@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => $challenge,
        'recovery_code' => '0000000001',
    ])->assertOk()
        ->assertJsonPath('used_recovery_code', true)
        ->assertJsonPath('recovery_codes_remaining', 7);
});

it('refuses to reuse a consumed recovery code', function () {
    makeAdminWith2fa('2fa-reuse@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => adminChallenge('2fa-reuse@salamhayetimiz.az'),
        'recovery_code' => '0000000002',
    ])->assertOk();

    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => adminChallenge('2fa-reuse@salamhayetimiz.az'),
        'recovery_code' => '0000000002',
    ])->assertStatus(401)->assertJsonPath('error.code', 'wrong_recovery_code');
});

it('rejects an invalid or expired challenge token', function () {
    makeAdminWith2fa('2fa-chal@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => 'not-a-valid-challenge',
        'totp' => currentTotp(),
    ])->assertStatus(401)->assertJsonPath('error.code', 'challenge_expired');
});

it('requires exactly one of totp or recovery_code', function () {
    makeAdminWith2fa('2fa-oneof@salamhayetimiz.az');
    $challenge = adminChallenge('2fa-oneof@salamhayetimiz.az');

    // neither
    $this->postJson('/admin/v1/auth/2fa/verify', ['challenge_token' => $challenge])->assertStatus(422);

    // both
    $this->postJson('/admin/v1/auth/2fa/verify', [
        'challenge_token' => $challenge,
        'totp' => currentTotp(),
        'recovery_code' => '0000000003',
    ])->assertStatus(422);
});
