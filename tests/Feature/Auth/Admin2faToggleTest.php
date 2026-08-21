<?php

use App\Domain\Admin\Services\SettingsService;

/*
| Global Require-2FA toggle (runtime setting). Default is OFF → admin login is email + password only;
| when ON, login returns the 2FA challenge. The toggle is read/written via /admin/v1/settings
| (system.settings.manage).
*/

it('logs an admin in directly when Require-2FA is off (default)', function () {
    makeAdminWith2fa('toggle-off@salamhayetimiz.az');

    $res = $this->postJson('/admin/v1/auth/login', [
        'email' => 'toggle-off@salamhayetimiz.az',
        'password' => 'password-12chars',
    ])->assertOk()
        ->assertJsonPath('two_factor_required', false)
        ->assertJsonStructure(['access_token', 'admin'])
        ->json();

    // the returned token is a real, usable admin session
    $this->getJson('/admin/v1/auth/me', bearer($res['access_token']))
        ->assertOk()->assertJsonPath('email', 'toggle-off@salamhayetimiz.az');
});

it('returns a 2FA challenge when Require-2FA is on', function () {
    app(SettingsService::class)->set(SettingsService::REQUIRE_2FA, '1');
    makeAdminWith2fa('toggle-on@salamhayetimiz.az');

    $this->postJson('/admin/v1/auth/login', [
        'email' => 'toggle-on@salamhayetimiz.az',
        'password' => 'password-12chars',
    ])->assertOk()
        ->assertJsonPath('two_factor_required', true)
        ->assertJsonStructure(['challenge_token', 'requires_totp']);
});

it('lets system.settings.manage read + flip the 2FA toggle (security group), and forbids others', function () {
    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/settings')
        ->assertOk()->assertJsonStructure(['groups' => [['group', 'fields', 'values']]]);

    $this->actingAs(makeSuperAdmin('s2@salamhayetimiz.az'), 'admin')
        ->patchJson('/admin/v1/settings/security', ['require_2fa' => true])
        ->assertOk()->assertJsonPath('values.require_2fa', true);

    expect(app(SettingsService::class)->bool(SettingsService::REQUIRE_2FA))->toBeTrue();

    $this->actingAs(makeAdminRole('finance'), 'admin')->getJson('/admin/v1/settings')->assertStatus(403);
    $this->actingAs(makeAdminRole('operator'), 'admin')
        ->patchJson('/admin/v1/settings/security', ['require_2fa' => false])->assertStatus(403);
});
