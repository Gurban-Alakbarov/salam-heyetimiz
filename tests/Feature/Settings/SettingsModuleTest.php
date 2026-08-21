<?php

use App\Domain\Admin\Services\SettingsService;
use Illuminate\Support\Facades\DB;

/*
| System Settings module — DB-driven grouped catalog, encrypted secrets, cache, super-admin only.
*/

it('returns the grouped settings catalog to a super admin and 403 to others', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/settings')
        ->assertOk()
        ->assertJsonStructure(['groups' => [['group', 'fields' => [['key', 'type', 'label']], 'values']], 'readonly']);

    $this->actingAs(makeAdminRole('finance'), 'admin')->getJson('/admin/v1/settings')->assertStatus(403);
    $this->actingAs(makeAdminRole('operator'), 'admin')->patchJson('/admin/v1/settings/general', ['app_name' => 'X'])->assertStatus(403);
});

it('persists a group with bool/int coercion and immediate effect', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')->patchJson('/admin/v1/settings/security', [
        'require_2fa' => true, 'max_login_attempts' => '7',
    ])->assertOk()->assertJsonPath('values.require_2fa', true)->assertJsonPath('values.max_login_attempts', 7);

    $s = app(SettingsService::class);
    expect($s->bool(SettingsService::REQUIRE_2FA))->toBeTrue()       // immediate (cache busted)
        ->and($s->value('security', 'max_login_attempts'))->toBe(7);
});

it('encrypts secrets, masks them on read, and keeps them on a blank update', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')->patchJson('/admin/v1/settings/payments', [
        'kapital_client_secret' => 'sk_live_TOPSECRET',
    ])->assertOk()->assertJsonPath('values.kapital_client_secret', '')->assertJsonPath('secrets_set.kapital_client_secret', true);

    // stored value is encrypted (not the plaintext), but decrypts back via the service
    $stored = DB::table('settings')->where('key', 'payments.kapital_client_secret')->value('value');
    expect($stored)->not->toBe('sk_live_TOPSECRET')
        ->and(app(SettingsService::class)->value('payments', 'kapital_client_secret'))->toBe('sk_live_TOPSECRET');

    // a blank secret on the next save keeps the existing one
    $this->actingAs($super, 'admin')->patchJson('/admin/v1/settings/payments', ['kapital_client_secret' => ''])->assertOk();
    expect(app(SettingsService::class)->value('payments', 'kapital_client_secret'))->toBe('sk_live_TOPSECRET');
});

it('rejects an unknown settings group with 404', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')->patchJson('/admin/v1/settings/nope', ['x' => 'y'])->assertStatus(404);
});

it('serves live system health to a super admin and 403 to others', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')->getJson('/admin/v1/system/health')
        ->assertOk()
        ->assertJsonStructure(['app' => ['laravel', 'php'], 'database' => ['ok'], 'redis', 'disk', 'memory', 'traccar' => ['status', 'device_count']]);

    $this->actingAs(makeAdminRole('support'), 'admin')->getJson('/admin/v1/system/health')->assertStatus(403);
});
