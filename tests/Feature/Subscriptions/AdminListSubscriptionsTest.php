<?php

use App\Domain\Subscriptions\Enums\SubscriptionStatus;

/*
| GET /admin/v1/subscriptions — adminListSubscriptions (status + expires_within_days filters).
*/

it('lists all subscriptions for an admin', function () {
    $admin = makeSuperAdmin();
    $u1 = makeUser('+994500000090');
    $u2 = makeUser('+994500000091');
    makeSubscription(makeDeviceUser($u1, makeActiveDevice('SER-AL1', '+994700000090')));
    makeSubscription(makeDeviceUser($u2, makeActiveDevice('SER-AL2', '+994700000091')));

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/subscriptions')
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('filters by expires_within_days', function () {
    $admin = makeSuperAdmin();
    $u = makeUser('+994500000092');

    makeSubscription(makeDeviceUser($u, makeActiveDevice('SER-AL3', '+994700000092')), [
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(10),
    ]);
    makeSubscription(makeDeviceUser($u, makeActiveDevice('SER-AL4', '+994700000093')), [
        'status' => SubscriptionStatus::Active,
        'ends_at' => now()->addDays(80),
    ]);

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/subscriptions?expires_within_days=30')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('rejects a non-admin caller', function () {
    $user = makeUser('+994500000093');

    $this->actingAs($user)->getJson('/admin/v1/subscriptions')->assertStatus(401);
});
