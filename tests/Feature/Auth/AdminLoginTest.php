<?php

use App\Domain\Admin\Services\SettingsService;

/*
| POST /admin/v1/auth/login — adminLogin (step 1: email + password → 2FA challenge; lockout).
| These tests exercise the 2FA path, so enable the global Require-2FA toggle (default is OFF).
*/

beforeEach(fn () => app(SettingsService::class)->set(SettingsService::REQUIRE_2FA, '1'));

it('returns a 2FA challenge for valid credentials', function () {
    makeAdminWith2fa('login-ok@salamhayetimiz.az');

    $response = $this->postJson('/admin/v1/auth/login', [
        'email' => 'login-ok@salamhayetimiz.az',
        'password' => 'password-12chars',
    ]);

    $response->assertOk()
        ->assertJsonPath('expires_in_seconds', 300)
        ->assertJsonPath('requires_totp', true)
        ->assertJsonStructure(['challenge_token', 'expires_in_seconds', 'requires_totp']);
});

it('rejects a wrong password with invalid_credentials', function () {
    makeAdminWith2fa('login-bad@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/login', [
        'email' => 'login-bad@salamhayetimiz.az',
        'password' => 'wrong-password',
    ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
});

it('rejects an unknown email with invalid_credentials (no enumeration)', function () {
    $this->postJson('/admin/v1/auth/login', [
        'email' => 'ghost@salamhayetimiz.az',
        'password' => 'password-12chars',
    ])->assertStatus(401)->assertJsonPath('error.code', 'invalid_credentials');
});

it('locks the account after too many failed attempts', function () {
    makeAdminWith2fa('login-lock@salamhayetimiz.az');

    for ($i = 1; $i <= 5; $i++) {
        $this->postJson('/admin/v1/auth/login', [
            'email' => 'login-lock@salamhayetimiz.az',
            'password' => 'wrong-password',
        ])->assertJsonPath('error.code', 'invalid_credentials');
    }

    // Even the correct password is now refused while the lock window is open.
    $this->postJson('/admin/v1/auth/login', [
        'email' => 'login-lock@salamhayetimiz.az',
        'password' => 'password-12chars',
    ])->assertStatus(401)->assertJsonPath('error.code', 'account_locked');
});
