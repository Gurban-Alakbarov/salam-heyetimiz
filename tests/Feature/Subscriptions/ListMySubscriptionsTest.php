<?php

use App\Domain\Subscriptions\Enums\SubscriptionStatus;

/*
| GET /v1/subscriptions — listMySubscriptions (only the caller's, cursor envelope, status filter).
*/

it('lists only the caller’s own subscriptions', function () {
    $me = makeUser('+994500000001');
    $other = makeUser('+994500000002');

    $myDevice = makeActiveDevice('SER-MINE', '+994700000010');
    $otherDevice = makeActiveDevice('SER-OTHER', '+994700000011');

    $myDu = makeDeviceUser($me, $myDevice);
    $otherDu = makeDeviceUser($other, $otherDevice);

    $mine = makeSubscription($myDu);
    makeSubscription($otherDu);

    $response = $this->actingAs($me, 'user')->getJson('/v1/subscriptions');

    $response->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $mine->id)
        ->assertJsonPath('data.0.user_id', $me->id)
        ->assertJsonPath('page.has_more', false);
});

it('filters by status', function () {
    $me = makeUser('+994500000003');
    $device = makeActiveDevice('SER-S', '+994700000012');
    $du = makeDeviceUser($me, $device);

    makeSubscription($du, ['status' => SubscriptionStatus::Active]);
    // a second device_user so the unique (device_user_id) constraint is respected
    $device2 = makeActiveDevice('SER-S2', '+994700000013');
    $du2 = makeDeviceUser($me, $device2);
    makeSubscription($du2, ['status' => SubscriptionStatus::Expired]);

    $this->actingAs($me, 'user')->getJson('/v1/subscriptions?status=expired')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'expired');
});
