<?php

use App\Domain\Payments\Models\Order;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Services\SubscriptionService;

/*
| O3 — an order line is one thing (quantity 1 only) and return_url can only lead back into the app or to an
| allow-listed https host. O4 — the subject of a subscription line is the subscription, whatever the line type:
| a second open order for it is never created, and one order cannot carry the same subject twice.
*/

function orderAs($test, $user, array $payload, string $key)
{
    return $test->actingAs($user, 'user')->postJson('/v1/orders', $payload, ['Idempotency-Key' => $key]);
}

function subLine(int $id, string $type = 'sub_main', ?int $quantity = 1): array
{
    return array_filter(['item_type' => $type, 'referenced_id' => $id, 'quantity' => $quantity], fn ($v) => $v !== null);
}

it('accepts only quantity 1 (and defaults to it)', function () {
    $user = makeUser();

    orderAs($this, $user, ['purpose' => 'sub_main', 'items' => [subLine(1, quantity: 10)]], 'o3-q10')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.quantity'], 'error.fields');

    $res = orderAs($this, $user, ['purpose' => 'sub_main', 'items' => [subLine(1, quantity: null)]], 'o3-q-default')->assertCreated();
    expect(Order::query()->findOrFail($res->json('id'))->items()->first()->quantity)->toBe(1);
});

it('only lets return_url lead back into the app or to an allow-listed https host', function (string $url, int $status) {
    config(['integrations.kapital.allowed_return_hosts' => ['sezam.salam.az']]);

    $res = orderAs($this, makeUser(), ['purpose' => 'sub_main', 'items' => [subLine(1)], 'return_url' => $url], 'o3-'.md5($url));

    $res->assertStatus($status);
    if ($status === 422) {
        $res->assertJsonValidationErrors(['return_url'], 'error.fields');
    }
})->with([
    'app scheme' => ['salam://payment/return', 201],
    'allowed https host' => ['https://sezam.salam.az/odenis', 201],
    'foreign host' => ['https://evil.example/phish', 422],
    'plain http' => ['http://sezam.salam.az/odenis', 422],
    'credentials trick' => ['https://sezam.salam.az@evil.example/', 422],
    'javascript' => ['javascript:alert(1)', 422],
]);

it('never opens a second order for the same subscription under another line type', function () {
    $owner = makeUser('+994501040001');
    $device = makeOwnedDevice($owner, 'SER-O4-1', '+994700040001');
    $row = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();
    $sub = app(SubscriptionService::class)->createPending($row, SubscriptionTier::Main);

    $first = orderAs($this, $owner, ['purpose' => 'sub_main', 'items' => [subLine($sub->id, 'sub_main')]], 'o4-a')->assertCreated();
    $second = orderAs($this, $owner, ['purpose' => 'sub_renewal', 'items' => [subLine($sub->id, 'sub_renewal')]], 'o4-b');

    expect($second->json('id'))->toBe($first->json('id'))
        ->and(Order::query()->count())->toBe(1);
});

it('refuses the same subscription twice in one order', function () {
    orderAs($this, makeUser(), ['purpose' => 'bundle', 'items' => [subLine(7, 'sub_main'), subLine(7, 'sub_renewal')]], 'o4-dup')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items'], 'error.fields');

    expect(Order::query()->count())->toBe(0);
});
