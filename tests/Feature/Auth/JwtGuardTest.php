<?php

use App\Domain\Users\Enums\UserStatus;

/*
| The two disjoint JWT guards (R-API-03): a mobile token opens mobile routes only; an admin
| token opens admin routes only; neither is interchangeable; inactive accounts are rejected.
*/

it('admits a valid mobile token to a protected mobile route', function () {
    $user = makeUser('+994505550001');

    $this->withHeaders(bearer(userAccessToken($user)))
        ->getJson('/v1/subscriptions')
        ->assertOk()
        ->assertJsonStructure(['data', 'page']);
});

it('rejects a protected mobile route without a token', function () {
    $this->getJson('/v1/subscriptions')->assertStatus(401);
});

it('rejects a garbage bearer token', function () {
    $this->withHeaders(bearer('not.a.jwt'))->getJson('/v1/subscriptions')->assertStatus(401);
});

it('does not accept an admin token on a mobile route (disjoint guards)', function () {
    $admin = makeSuperAdmin();

    $this->withHeaders(bearer(adminAccessToken($admin)))
        ->getJson('/v1/subscriptions')
        ->assertStatus(401);
});

it('does not accept a mobile token on an admin route (disjoint guards)', function () {
    $user = makeUser('+994505550002');

    $this->withHeaders(bearer(userAccessToken($user)))
        ->getJson('/admin/v1/subscriptions')
        ->assertStatus(401);
});

it('admits a valid admin token to a protected admin route', function () {
    $admin = makeSuperAdmin();

    $this->withHeaders(bearer(adminAccessToken($admin)))
        ->getJson('/admin/v1/subscriptions')
        ->assertOk()
        ->assertJsonStructure(['data', 'page']);
});

it('rejects a token for a blocked user', function () {
    $user = makeUser('+994505550003');
    $token = userAccessToken($user);
    $user->forceFill(['status' => UserStatus::Blocked->value])->save();

    $this->withHeaders(bearer($token))->getJson('/v1/subscriptions')->assertStatus(401);
});
