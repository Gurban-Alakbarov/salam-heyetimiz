<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\SettingsCatalog;
use App\Domain\Admin\Settings\SettingsConfigBridge;
use App\Domain\DeviceComm\Adapters\FakeDeviceSmsGateway;
use App\Domain\Mail\EmailType;
use Illuminate\Support\Facades\Http;

it('offers no setting that nothing reads', function () {
    $keys = collect(SettingsCatalog::groups())
        ->flatMap(fn (array $defs, string $group) => collect($defs)->keys()->map(fn (string $key) => "{$group}.{$key}"));

    expect(SettingsCatalog::groupNames())->not->toContain('sms')
        ->and(array_keys(SettingsCatalog::groups()['traccar']))->toBe(['offline_threshold_minutes'])
        ->and($keys->all())->not->toContain(
            'general.timezone', 'general.default_language', 'security.session_timeout_minutes', 'security.max_sessions',
            'email.tpl_welcome', 'otp.brute_force_protection', 'payments.save_card_enabled', 'subscriptions.grace_period_days',
        );
});

it('gives every mail the system sends a subject field', function (EmailType $type) {
    expect(SettingsCatalog::definition('email', $type->subjectKey()))->not->toBeNull()
        ->and($type->defaultSubject())->not->toContain('Həyətimiz');
})->with(array_filter(EmailType::cases(), fn (EmailType $type) => ! in_array($type, [EmailType::RegistrationOtp, EmailType::LoginOtp], true)));

it('moves the offline window with the panel setting', function () {
    $settings = app(SettingsService::class);
    $settings->setGroup('traccar', ['offline_threshold_minutes' => 40]);

    app(SettingsConfigBridge::class)->apply($settings);

    expect(config('domain.devices.offline_threshold_minutes'))->toBe(40);
});

it('refuses an offline window of zero', function () {
    seedRbac();

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->patchJson('/admin/v1/settings/traccar', ['offline_threshold_minutes' => 0])
        ->assertStatus(422);
});

it('tests the Traccar connection the devices really use', function () {
    seedRbac();
    config(['integrations.traccar.base_url' => 'http://traccar.test', 'integrations.traccar.api_token' => 'env-token']);
    Http::fake(['traccar.test/*' => Http::response(['version' => '6.0'])]);

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/settings/traccar/test')
        ->assertOk()->assertJsonPath('ok', true);
    $this->getJson('/admin/v1/system/health')
        ->assertOk()->assertJsonPath('traccar.status', 'connected');

    Http::assertSent(fn ($request) => $request->url() === 'http://traccar.test/api/server'
        && $request->hasHeader('Authorization', 'Bearer env-token'));
});

it('says so when no SMS provider is connected', function () {
    seedRbac();
    config(['integrations.sms.provider' => 'fake']);

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->getJson('/admin/v1/system/health')
        ->assertOk()->assertJsonPath('sms.configured', false)->assertJsonPath('sms.provider', 'fake');

    $this->postJson('/admin/v1/settings/sms/send-test', ['to' => '+994500000000'])
        ->assertOk()->assertJsonPath('ok', false);

    expect(app(FakeDeviceSmsGateway::class)->sent ?? [])->toBeEmpty();
});

it('sends a real test SMS when a provider is connected', function () {
    seedRbac();
    config(['integrations.sms.provider' => 'lsim', 'integrations.sms.base_url' => 'http://sms.test']);

    $this->actingAs(makeSuperAdmin(), 'admin')
        ->postJson('/admin/v1/settings/sms/send-test', ['to' => '+994500000000'])
        ->assertOk()->assertJsonPath('ok', true);

    $this->getJson('/admin/v1/system/health')->assertOk()->assertJsonPath('sms.configured', true);
});

it('ignores removed settings when an old backup is restored', function () {
    $settings = app(SettingsService::class);

    $settings->restoreRaw(['sms.provider' => 'lsim', 'general.force_update' => '1', 'general.app_name' => 'Köhnə']);

    expect($settings->get('sms.provider'))->toBeNull()
        ->and($settings->get('general.force_update'))->toBeNull()
        ->and($settings->get('general.app_name'))->toBe('Köhnə');
});
