<?php

use App\Domain\Users\Models\User;
use Illuminate\Support\Str;

/*
| Phase 3 — guest + authenticated one-shot bootstrap. Unified envelope; the /v1/me data object is
| extensible (future sections add keys without breaking the contract).
*/

function makeVerifiedUser(array $overrides = []): User
{
    // ->refresh() so the in-memory model carries every column (mirrors the guard's find() in prod).
    return User::query()->create(array_merge([
        'full_name' => 'Test İstifadəçi',
        'phone' => '+994501112233',
        'phone_country' => 'AZ',
        'email' => 'me@test.az',
        'email_verified_at' => now(),
        'preferred_language' => 'az',
        'status' => 'active',
    ], $overrides))->refresh();
}

it('serves guest bootstrap without authentication', function () {
    test()->getJson('/v1/bootstrap')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure([
            'success', 'message',
            'data' => [
                'app' => ['maintenance_mode', 'min_version', 'latest_version', 'force_update'],
                'otp' => ['length', 'ttl_seconds', 'resend_seconds'],
                'public_settings' => ['brand', 'default_locale', 'default_timezone', 'currency'],
                'feature_flags',
                'support' => ['email', 'phone'],
            ],
            'meta', 'errors',
        ]);
});

it('serves the authenticated current-user bootstrap in one shot', function () {
    $user = makeVerifiedUser();
    $user->userDevices()->create([
        'install_uuid' => (string) Str::uuid(), 'platform' => 'ios', 'app_version' => '1.0.0', 'last_seen_at' => now(),
    ]);

    $res = test()->actingAs($user, 'user')->getJson('/v1/me')->assertOk();

    $res->assertJsonPath('success', true)
        ->assertJsonPath('data.registration_completed', true)
        ->assertJsonPath('data.email_verified', true)
        ->assertJsonPath('data.has_password', false)
        ->assertJsonPath('data.phone', '+994501112233')
        ->assertJsonPath('data.permissions', [])
        ->assertJsonPath('data.unread_notifications_count', 0)
        ->assertJsonStructure([
            'data' => [
                'user' => ['id', 'phone', 'email', 'email_verified_at'],
                'registration_completed', 'email_verified', 'phone', 'has_password', 'avatar',
                'locale', 'timezone',
                'app' => ['maintenance_mode', 'min_version', 'latest_version', 'force_update'],
                'feature_flags', 'permissions', 'unread_notifications_count',
                'user_devices' => [['id', 'platform', 'device_model', 'app_version', 'biometric_enrolled']],
                'active_subscriptions',
            ],
        ]);

    expect($res->json('data.user_devices'))->toHaveCount(1);
});

it('requires authentication for /v1/me', function () {
    test()->getJson('/v1/me')->assertStatus(401);
});

it('reports has_password=true once a password is set (forward-compat)', function () {
    $user = makeVerifiedUser(['email' => 'pw@test.az', 'phone' => '+994509998877', 'password' => bcrypt('secret123')]);

    test()->actingAs($user, 'user')->getJson('/v1/me')
        ->assertOk()
        ->assertJsonPath('data.has_password', true);
});
