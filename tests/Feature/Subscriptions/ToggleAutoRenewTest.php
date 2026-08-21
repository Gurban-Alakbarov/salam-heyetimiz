<?php

/*
| PATCH /v1/subscriptions/{id}/auto-renew — toggleAutoRenew (P2: enabling is 409 until tokenization).
*/

it('rejects enabling auto-renew while tokenization is unavailable (409)', function () {
    config()->set('domain.subscriptions.auto_renew_enabled', false);

    $me = makeUser('+994500000040');
    $device = makeActiveDevice('SER-A1', '+994700000040');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->patchJson("/v1/subscriptions/{$sub->id}/auto-renew", [
        'auto_renew' => true,
    ])->assertStatus(409)->assertJsonPath('error.code', 'auto_renew_unavailable');

    expect($sub->fresh()->auto_renew)->toBeFalse();
});

it('allows disabling auto-renew', function () {
    $me = makeUser('+994500000041');
    $device = makeActiveDevice('SER-A2', '+994700000041');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du, ['auto_renew' => true]);

    $this->actingAs($me, 'user')->patchJson("/v1/subscriptions/{$sub->id}/auto-renew", [
        'auto_renew' => false,
    ])->assertOk()->assertJsonPath('auto_renew', false);

    expect($sub->fresh()->auto_renew)->toBeFalse();
});

it('requires the auto_renew flag', function () {
    $me = makeUser('+994500000042');
    $device = makeActiveDevice('SER-A3', '+994700000042');
    $du = makeDeviceUser($me, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->patchJson("/v1/subscriptions/{$sub->id}/auto-renew", [])
        ->assertStatus(422);
});

it('hides another user’s subscription behind a 404', function () {
    $me = makeUser('+994500000043');
    $other = makeUser('+994500000044');
    $device = makeActiveDevice('SER-A4', '+994700000043');
    $du = makeDeviceUser($other, $device);
    $sub = makeSubscription($du);

    $this->actingAs($me, 'user')->patchJson("/v1/subscriptions/{$sub->id}/auto-renew", [
        'auto_renew' => false,
    ])->assertStatus(404);
});
