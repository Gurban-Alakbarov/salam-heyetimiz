<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Services\SettingsService;
use App\Domain\Audit\Models\AuditLog;
use Illuminate\Support\Facades\Hash;

const STRONG_PASSWORD = 'Yeni-parol-2026';

function adminLoginToken(string $email, string $password): string
{
    return (string) test()->postJson('/admin/v1/auth/login', ['email' => $email, 'password' => $password])
        ->assertOk()->json('access_token');
}

it('changes the password, ends older sessions and lets the new one sign in', function () {
    seedRbac();
    $admin = makeSuperAdmin();
    $token = adminLoginToken($admin->email, 'password-12chars');

    $this->travel(2)->seconds();

    $this->withToken($token)->postJson('/admin/v1/auth/password', [
        'current_password' => 'password-12chars',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertNoContent();

    expect(Hash::check(STRONG_PASSWORD, (string) $admin->refresh()->password))->toBeTrue()
        ->and(AuditLog::query()->where('action', 'admin.password_changed')->exists())->toBeTrue();

    app('auth')->forgetGuards();
    $this->withToken($token)->getJson('/admin/v1/auth/me')->assertUnauthorized();

    $this->travel(2)->seconds();
    app('auth')->forgetGuards();
    $this->withToken(adminLoginToken($admin->email, STRONG_PASSWORD))->getJson('/admin/v1/auth/me')->assertOk();
});

it('refuses a wrong current password and keeps the old one', function () {
    seedRbac();
    $admin = makeSuperAdmin();

    $this->actingAs($admin, 'admin')->postJson('/admin/v1/auth/password', [
        'current_password' => 'not-the-password',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertStatus(422)->assertJsonValidationErrors(['current_password'], 'error.fields');

    expect(Hash::check('password-12chars', (string) $admin->refresh()->password))->toBeTrue()
        ->and($admin->password_changed_at)->toBeNull();
});

it('applies the password rules from settings', function (string $password, string $key = '', string $value = '') {
    seedRbac();
    if ($key !== '') {
        app(SettingsService::class)->setGroup('security', [$key => $value]);
    }

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/auth/password', [
        'current_password' => 'password-12chars',
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertStatus(422)->assertJsonValidationErrors(['password'], 'error.fields');
})->with([
    'too short' => ['Qisa-1'],
    'no uppercase' => ['kicik-herfler-2026'],
    'no number' => ['Reqemsiz-parol-uzun'],
    'same as the current one' => ['password-12chars', 'password_require_uppercase', '0'],
    'symbol required' => ['SimvolsuzParol2026', 'password_require_symbol', '1'],
    'longer minimum' => ['Qisa-parol-2026', 'password_min_length', '20'],
]);

it('never lets the minimum drop below 12, in the panel or in a stored value', function () {
    seedRbac();
    $super = makeSuperAdmin();

    $this->actingAs($super, 'admin')->patchJson('/admin/v1/settings/security', ['password_min_length' => 5])
        ->assertStatus(422);

    app(SettingsService::class)->setGroup('security', ['password_min_length' => 5]);
    $this->actingAs($super, 'admin')->postJson('/admin/v1/auth/password', [
        'current_password' => 'password-12chars',
        'password' => 'Qisa-1',
        'password_confirmation' => 'Qisa-1',
    ])->assertStatus(422)->assertJsonValidationErrors(['password'], 'error.fields');
});

it('applies the rules when an admin is created', function () {
    seedRbac();
    $payload = ['email' => 'yeni@sezam.salam.az', 'name' => 'Yeni Admin', 'role' => 'operator'];

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson('/admin/v1/admins', $payload + ['password' => 'kicik-herfler-2026'])
        ->assertStatus(422)->assertJsonValidationErrors(['password'], 'error.fields');

    $this->postJson('/admin/v1/admins', $payload + ['password' => STRONG_PASSWORD])->assertSuccessful();
    expect(AdminUser::query()->where('email', 'yeni@sezam.salam.az')->exists())->toBeTrue();
});

it('does not let an impersonating admin change the password of the other admin', function () {
    seedRbac();
    $super = makeSuperAdmin();
    $operator = makeAdminRole('operator');

    $token = (string) $this->withToken(adminLoginToken($super->email, 'password-12chars'))
        ->postJson("/admin/v1/admins/{$operator->id}/impersonate")->assertOk()->json('access_token');

    app('auth')->forgetGuards();
    $this->withToken($token)->postJson('/admin/v1/auth/password', [
        'current_password' => 'password-12chars',
        'password' => STRONG_PASSWORD,
        'password_confirmation' => STRONG_PASSWORD,
    ])->assertForbidden();

    expect(Hash::check('password-12chars', (string) $operator->refresh()->password))->toBeTrue();
});
