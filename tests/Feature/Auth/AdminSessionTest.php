<?php

use App\Domain\Auth\Support\RecoveryCodes;

/*
| Admin session endpoints: adminMe, adminLogout, regenerateRecoveryCodes (re-auth, R-SEC-06).
*/

it('returns the current admin profile', function () {
    $admin = makeAdminWith2fa('me@salamhayetimiz.az');

    $this->withHeaders(bearer(adminAccessToken($admin)))
        ->getJson('/admin/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('email', 'me@salamhayetimiz.az')
        ->assertJsonPath('role', 'super_admin')
        ->assertJsonPath('is_2fa_enabled', true);
});

it('logs out with a 204', function () {
    $admin = makeAdminWith2fa('logout@salamhayetimiz.az');

    $this->withHeaders(bearer(adminAccessToken($admin)))
        ->postJson('/admin/v1/auth/logout')
        ->assertStatus(204);
});

it('regenerates the recovery-code set after a fresh TOTP', function () {
    $admin = makeAdminWith2fa('regen@salamhayetimiz.az');

    $response = $this->withHeaders(bearer(adminAccessToken($admin)))
        ->postJson('/admin/v1/auth/recovery-codes', ['totp' => currentTotp()]);

    $response->assertOk()
        ->assertJsonCount(8, 'codes')
        ->assertJsonStructure(['codes', 'generated_at']);

    // The previous set is invalidated; the new plaintext verifies against the stored hashes.
    $fresh = $admin->fresh();
    $newCode = $response->json('codes.0');
    expect(RecoveryCodes::match((array) $fresh->recovery_codes_hashes, $newCode))->not->toBeNull()
        ->and(RecoveryCodes::match((array) $fresh->recovery_codes_hashes, '0000000001'))->toBeNull();
});

it('rejects recovery-code regeneration with a wrong TOTP', function () {
    $admin = makeAdminWith2fa('regen-bad@salamhayetimiz.az');

    $this->withHeaders(bearer(adminAccessToken($admin)))
        ->postJson('/admin/v1/auth/recovery-codes', ['totp' => '000000'])
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'wrong_totp');
});

it('requires authentication for the admin session routes', function () {
    $this->getJson('/admin/v1/auth/me')->assertStatus(401);
    $this->postJson('/admin/v1/auth/logout')->assertStatus(401);
});
