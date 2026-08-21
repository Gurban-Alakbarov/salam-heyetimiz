<?php

use App\Domain\Admin\Models\SettingsVersion;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\SettingsConfigBridge;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Exceptions\OtpThrottleException;
use App\Domain\Auth\Services\OtpService;

/*
| Settings v2 — rich audit, version history, import/export, config bridge, OTP throttle, force-logout, ops.
*/

it('records a rich audit entry (old→new + ip) and a version snapshot on update', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/settings/general', ['app_name' => 'Yeni Ad'])
        ->assertOk();

    $log = AuditLog::query()->where('action', 'settings.updated')->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->ip)->not->toBeNull()
        ->and(collect($log->payload['changes'])->firstWhere('key', 'app_name'))
        ->toMatchArray(['key' => 'app_name', 'new' => 'Yeni Ad']);

    expect(SettingsVersion::query()->where('reason', 'update')->count())->toBeGreaterThanOrEqual(1);
});

it('redacts secrets in the audit diff', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/settings/payments', ['kapital_client_secret' => 'sk_SECRET'])
        ->assertOk();

    $log = AuditLog::query()->where('action', 'settings.updated')->latest('id')->first();
    $change = collect($log->payload['changes'])->firstWhere('key', 'kapital_client_secret');
    expect($change['new'])->not->toContain('sk_SECRET')->and($change['old'])->toBe('••••••');
});

it('exports and re-imports settings as JSON', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $export = $this->actingAs($super, 'admin')->getJson('/admin/v1/settings/export')
        ->assertOk()->assertJsonStructure(['_meta' => ['key_count'], 'settings'])->json();
    expect($export['settings'])->toHaveKey('general.app_name');

    $this->actingAs($super, 'admin')->postJson('/admin/v1/settings/import', [
        'settings' => ['general.app_name' => 'Idxal Edilmiş'],
    ])->assertOk()->assertJsonPath('ok', true);

    expect(app(SettingsService::class)->value('general', 'app_name'))->toBe('Idxal Edilmiş');
    expect(SettingsVersion::query()->where('reason', 'import')->count())->toBe(1);
});

it('lists, compares and restores a previous version', function () {
    seedRbac();
    $super = makeSuperAdmin();
    $s = app(SettingsService::class);

    $this->actingAs($super, 'admin')->patchJson('/admin/v1/settings/general', ['app_name' => 'Versiya A'])->assertOk();
    $verA = SettingsVersion::query()->latest('id')->first()->id;
    $this->actingAs($super, 'admin')->patchJson('/admin/v1/settings/general', ['app_name' => 'Versiya B'])->assertOk();
    $verB = SettingsVersion::query()->latest('id')->first()->id;
    expect($s->value('general', 'app_name'))->toBe('Versiya B');

    // compare
    $cmp = $this->actingAs($super, 'admin')
        ->getJson("/admin/v1/settings/versions/compare?from={$verA}&to={$verB}")->assertOk()->json();
    expect(collect($cmp['diff'])->pluck('key'))->toContain('general.app_name');

    // restore A → app_name returns to "Versiya A"
    $this->actingAs($super, 'admin')->postJson("/admin/v1/settings/versions/{$verA}/restore")->assertOk();
    expect(app(SettingsService::class)->value('general', 'app_name'))->toBe('Versiya A');
    expect(SettingsVersion::query()->where('reason', 'restore')->count())->toBe(1);
});

it('overlays runtime settings onto config via the bridge', function () {
    seedRbac();
    app(SettingsService::class)->setGroup('security', ['jwt_admin_ttl_seconds' => 999]);
    app(SettingsService::class)->setGroup('otp', ['otp_length' => 8]);

    app(SettingsConfigBridge::class)->apply(app(SettingsService::class));

    expect(config('domain.auth.access.admin.ttl_seconds'))->toBe(999)
        ->and(config('domain.auth.otp.length'))->toBe(8);
});

it('enforces the OTP hourly limit when configured (default unlimited)', function () {
    app(SettingsService::class)->setGroup('otp', ['otp_hourly_limit' => 2]);
    $otp = app(OtpService::class);
    $purpose = OtpPurpose::cases()[0];

    $otp->issue('+994500000777', $purpose, '127.0.0.1', 'az');
    $otp->issue('+994500000777', $purpose, '127.0.0.1', 'az');

    expect(fn () => $otp->issue('+994500000777', $purpose, '127.0.0.1', 'az'))
        ->toThrow(OtpThrottleException::class);
});

it('force-logout invalidates admin tokens issued before the cutoff (guard)', function () {
    seedRbac();
    makeAdminWith2fa('force@salamhayetimiz.az');
    $token = $this->postJson('/admin/v1/auth/login', [
        'email' => 'force@salamhayetimiz.az', 'password' => 'password-12chars',
    ])->assertOk()->json('access_token');

    // The guard resolves the token now (fresh Request + fresh guard instance each time — no request caching).
    $req = Illuminate\Http\Request::create('/x');
    $req->headers->set('Authorization', "Bearer {$token}");
    expect(app(App\Domain\Auth\Guards\JwtRequestGuard::class)->resolveAdmin($req))->not->toBeNull();

    // cutoff in the future → the existing token's iat is older → rejected
    app(SettingsService::class)->set('security.force_logout_at', (string) (now()->timestamp + 60));
    $req2 = Illuminate\Http\Request::create('/x');
    $req2->headers->set('Authorization', "Bearer {$token}");
    expect(app(App\Domain\Auth\Guards\JwtRequestGuard::class)->resolveAdmin($req2))->toBeNull();
});

it('force-logout endpoint revokes user refresh tokens and is super-only', function () {
    seedRbac();
    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/settings/security/force-logout')
        ->assertOk()->assertJsonStructure(['ok', 'cutoff', 'user_tokens_revoked']);

    $this->actingAs(makeAdminRole('finance'), 'admin')->postJson('/admin/v1/settings/security/force-logout')->assertStatus(403);
});

it('serves honest test actions and live traccar status', function () {
    seedRbac();
    $super = makeSuperAdmin();

    // email not configured → ok:false (no fake success)
    $this->actingAs($super, 'admin')->postJson('/admin/v1/settings/email/send-test', ['to' => 'a@b.com'])
        ->assertOk()->assertJsonPath('ok', false);

    // sms has no provider integrated → honest ok:false
    $this->actingAs($super, 'admin')->postJson('/admin/v1/settings/sms/send-test', ['to' => '+994500000000'])
        ->assertOk()->assertJsonPath('ok', false);

    // traccar status reads real DB values
    $this->actingAs($super, 'admin')->getJson('/admin/v1/settings/traccar/status')
        ->assertOk()->assertJsonStructure(['connection', 'device_count', 'last_webhook', 'last_command', 'last_ack', 'last_device_sync']);

    $this->actingAs(makeAdminRole('support'), 'admin')->getJson('/admin/v1/settings/traccar/status')->assertStatus(403);
});
