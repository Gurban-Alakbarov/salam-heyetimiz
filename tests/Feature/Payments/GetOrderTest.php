<?php

it('lets the payer view their own order detail', function () {
    $user = makeUser();
    $order = makePaidOrder($user);

    $this->actingAs($user, 'user')->getJson('/v1/orders/'.$order->id)
        ->assertStatus(200)
        ->assertJsonPath('id', $order->id)
        ->assertJsonPath('status', 'paid')
        ->assertJsonStructure(['id', 'reference', 'status', 'payments', 'refunds', 'timeline']);
});

it('returns 404 when a different user requests the order (no leakage — R-PAY-12)', function () {
    $owner = makeUser('+994501112233');
    $other = makeUser('+994559998877');
    $order = makePaidOrder($owner);

    $this->actingAs($other, 'user')->getJson('/v1/orders/'.$order->id)->assertStatus(404);
});

it('lets an admin view any order', function () {
    $user = makeUser();
    $admin = makeSuperAdmin();
    $order = makePaidOrder($user);

    $this->actingAs($admin, 'admin')->getJson('/admin/v1/orders/'.$order->id)
        ->assertStatus(200)
        ->assertJsonPath('id', $order->id);
});

it('lists the payer orders inside a {data, page} envelope', function () {
    $user = makeUser();
    makePaidOrder($user, 1200, 'KB-A');
    makePaidOrder($user, 600, 'KB-B');

    $this->actingAs($user, 'user')->getJson('/v1/orders')
        ->assertStatus(200)
        ->assertJsonStructure(['data' => [['id', 'reference', 'status']], 'page' => ['next_cursor', 'has_more', 'limit']])
        ->assertJsonCount(2, 'data');
});
