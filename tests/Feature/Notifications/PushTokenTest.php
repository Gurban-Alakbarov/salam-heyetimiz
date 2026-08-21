<?php

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;

/*
| PUT + DELETE /v1/notifications/push-token (openapi upsertPushToken / deletePushToken). The FCM token
| lives per-install on user_devices, resolved from the JWT `fp` (install_uuid); multi-device isolated.
*/

function installOf(User $user, string $uuid, ?string $token = null, array $o = []): UserDevice
{
    return UserDevice::query()->create(array_merge([
        'user_id' => $user->getKey(),
        'install_uuid' => $uuid,
        'platform' => 'ios',
        'push_token' => $token,
        'push_invalid' => false,
    ], $o));
}

it('registers a push token for the calling install (204)', function () {
    $user = makeUser('+994505550001');
    $device = installOf($user, 'inst-1');

    $this->withHeaders(bearer(userAccessToken($user, 'inst-1')))
        ->putJson('/v1/notifications/push-token', ['push_token' => 'fcm-token-abc-123'])
        ->assertStatus(204);

    $device->refresh();
    expect($device->push_token)->toBe('fcm-token-abc-123')
        ->and($device->push_invalid)->toBeFalse()
        ->and($device->push_token_updated_at)->not->toBeNull();
});

it('refreshes an existing token on a second upsert', function () {
    $user = makeUser('+994505550002');
    $device = installOf($user, 'inst-1', 'old-token-000000');

    $this->withHeaders(bearer(userAccessToken($user, 'inst-1')))
        ->putJson('/v1/notifications/push-token', ['push_token' => 'new-token-999999'])
        ->assertStatus(204);

    expect($device->refresh()->push_token)->toBe('new-token-999999');
});

it('clears push_invalid when the token is refreshed (re-activation)', function () {
    $user = makeUser('+994505550003');
    $device = installOf($user, 'inst-1', 'stale-token-0000', ['push_invalid' => true]);

    $this->withHeaders(bearer(userAccessToken($user, 'inst-1')))
        ->putJson('/v1/notifications/push-token', ['push_token' => 'fresh-token-1111'])
        ->assertStatus(204);

    $device->refresh();
    expect($device->push_invalid)->toBeFalse()
        ->and($device->push_token)->toBe('fresh-token-1111');
});

it('updates only the calling install and leaves other devices untouched', function () {
    $user = makeUser('+994505550004');
    $a = installOf($user, 'inst-a', 'token-a-original');
    $b = installOf($user, 'inst-b', 'token-b-original');

    $this->withHeaders(bearer(userAccessToken($user, 'inst-a')))
        ->putJson('/v1/notifications/push-token', ['push_token' => 'token-a-updated'])
        ->assertStatus(204);

    expect($a->refresh()->push_token)->toBe('token-a-updated')
        ->and($b->refresh()->push_token)->toBe('token-b-original');
});

it('de-registers only the calling install token (204) and preserves other devices', function () {
    $user = makeUser('+994505550005');
    $a = installOf($user, 'inst-a', 'token-a-live');
    $b = installOf($user, 'inst-b', 'token-b-live');

    $this->withHeaders(bearer(userAccessToken($user, 'inst-a')))
        ->deleteJson('/v1/notifications/push-token')
        ->assertStatus(204);

    expect($a->refresh()->push_token)->toBeNull()
        ->and($b->refresh()->push_token)->toBe('token-b-live');
});

it('requires authentication for both operations', function () {
    $this->putJson('/v1/notifications/push-token', ['push_token' => 'unauth-token-123'])->assertStatus(401);
    $this->deleteJson('/v1/notifications/push-token')->assertStatus(401);
});

it('validates the push_token body (required, 10..255)', function () {
    $user = makeUser('+994505550006');
    installOf($user, 'inst-1');
    $headers = bearer(userAccessToken($user, 'inst-1'));

    $this->withHeaders($headers)->putJson('/v1/notifications/push-token', [])->assertStatus(422);
    $this->withHeaders($headers)->putJson('/v1/notifications/push-token', ['push_token' => 'short'])->assertStatus(422);
    $this->withHeaders($headers)->putJson('/v1/notifications/push-token', ['push_token' => str_repeat('z', 256)])->assertStatus(422);
});

it('is a safe no-op when the JWT fingerprint matches no install', function () {
    $user = makeUser('+994505550007'); // no user_devices row created for this user
    $headers = bearer(userAccessToken($user, 'ghost-install'));

    $this->withHeaders($headers)->putJson('/v1/notifications/push-token', ['push_token' => 'orphan-token-123'])->assertStatus(204);
    $this->withHeaders($headers)->deleteJson('/v1/notifications/push-token')->assertStatus(204);

    // no phantom install row was created — identity is minted only at login
    expect(UserDevice::query()->where('user_id', $user->id)->count())->toBe(0);
});
