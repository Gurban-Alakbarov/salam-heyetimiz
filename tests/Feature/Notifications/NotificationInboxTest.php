<?php

use App\Domain\Notifications\Models\Notification;
use App\Domain\Users\Models\User;

/*
| In-app inbox (openapi listNotifications / markNotificationRead / markAllNotificationsRead): caller-scoped
| inapp rows, cursor pagination, unread_count, idempotent read. Push rows are delivery records (excluded).
*/

function inapp(User $user, array $o = []): Notification
{
    return Notification::query()->create(array_merge([
        'partition_key' => (int) now()->format('Ym'),
        'user_id' => $user->getKey(),
        'campaign_id' => null,
        'template_key' => 'device.opened',
        'channel' => 'inapp',
        'payload' => ['title' => 'Qonaq daxil oldu', 'body' => 'Kuryer açdı', 'type' => 'visitor_link_used', 'ids' => ['visitor_link_id' => 5, 'device_id' => 3]],
        'dedupe_key' => 'visitor_opened:'.uniqid(),
        'status' => 'sent',
        'sent_at' => now(),
    ], $o));
}

it('lists the caller inapp notifications newest first with unread_count, excluding push rows', function () {
    $user = makeUser('+994507000001');
    $a = inapp($user);
    $b = inapp($user);
    inapp($user, ['channel' => 'push', 'status' => 'queued', 'sent_at' => null, 'dedupe_key' => 'x:'.uniqid()]);

    $res = $this->withHeaders(bearer(userAccessToken($user)))->getJson('/v1/notifications')->assertOk();

    expect($res->json('data'))->toHaveCount(2)
        ->and($res->json('data.0.id'))->toBe((int) $b->id)   // newest first
        ->and($res->json('data.1.id'))->toBe((int) $a->id)
        ->and($res->json('data.0.channel'))->toBe('inapp')
        ->and($res->json('data.0.payload.type'))->toBe('visitor_link_used')
        ->and($res->json('data.0.payload.title'))->toBe('Qonaq daxil oldu')
        ->and($res->json('unread_count'))->toBe(2)
        ->and($res->json('page.has_more'))->toBeFalse();
});

it('never exposes another user notifications', function () {
    $me = makeUser('+994507000002');
    $other = makeUser('+994507000003');
    inapp($other);

    $res = $this->withHeaders(bearer(userAccessToken($me)))->getJson('/v1/notifications')->assertOk();
    expect($res->json('data'))->toHaveCount(0)->and($res->json('unread_count'))->toBe(0);
});

it('paginates with an opaque cursor without overlap', function () {
    $user = makeUser('+994507000004');
    foreach (range(1, 5) as $i) {
        inapp($user);
    }

    $p1 = $this->withHeaders(bearer(userAccessToken($user)))->getJson('/v1/notifications?limit=2')->assertOk();
    expect($p1->json('data'))->toHaveCount(2)->and($p1->json('page.has_more'))->toBeTrue();
    $cursor = $p1->json('page.next_cursor');
    expect($cursor)->not->toBeNull();

    $p2 = $this->withHeaders(bearer(userAccessToken($user)))->getJson('/v1/notifications?limit=2&cursor='.$cursor)->assertOk();
    expect($p2->json('data'))->toHaveCount(2);
    $overlap = collect($p1->json('data'))->pluck('id')->intersect(collect($p2->json('data'))->pluck('id'));
    expect($overlap)->toHaveCount(0);
});

it('marks a notification read (idempotent) and drops unread_count', function () {
    $user = makeUser('+994507000005');
    $n = inapp($user);
    $h = bearer(userAccessToken($user));

    $this->withHeaders($h)->postJson('/v1/notifications/'.$n->id.'/read')->assertStatus(204);
    $n->refresh();
    expect($n->status->value)->toBe('read')->and($n->read_at)->not->toBeNull();
    $firstReadAt = $n->read_at;

    $this->withHeaders($h)->postJson('/v1/notifications/'.$n->id.'/read')->assertStatus(204); // replay
    expect($n->refresh()->read_at->equalTo($firstReadAt))->toBeTrue();

    expect($this->withHeaders($h)->getJson('/v1/notifications')->json('unread_count'))->toBe(0);
});

it('returns 404 marking another user notification read (and leaves it untouched)', function () {
    $me = makeUser('+994507000006');
    $other = makeUser('+994507000007');
    $n = inapp($other);

    $this->withHeaders(bearer(userAccessToken($me)))->postJson('/v1/notifications/'.$n->id.'/read')->assertStatus(404);
    expect($n->refresh()->status->value)->toBe('sent');
});

it('marks all read (idempotent)', function () {
    $user = makeUser('+994507000008');
    inapp($user);
    inapp($user);
    inapp($user);
    $h = bearer(userAccessToken($user));

    $this->withHeaders($h)->postJson('/v1/notifications/read-all')->assertStatus(204);
    $res = $this->withHeaders($h)->getJson('/v1/notifications')->assertOk();
    expect($res->json('unread_count'))->toBe(0)
        ->and(collect($res->json('data'))->every(fn ($n) => $n['status'] === 'read'))->toBeTrue();

    $this->withHeaders($h)->postJson('/v1/notifications/read-all')->assertStatus(204); // idempotent
});

it('returns an empty inbox for a user with no notifications', function () {
    $user = makeUser('+994507000009');
    $res = $this->withHeaders(bearer(userAccessToken($user)))->getJson('/v1/notifications')->assertOk();
    expect($res->json('data'))->toHaveCount(0)
        ->and($res->json('unread_count'))->toBe(0)
        ->and($res->json('page.has_more'))->toBeFalse()
        ->and($res->json('page.next_cursor'))->toBeNull();
});

it('filters by status=read', function () {
    $user = makeUser('+994507000010');
    inapp($user);
    $read = inapp($user, ['status' => 'read', 'read_at' => now(), 'dedupe_key' => 'r:'.uniqid()]);

    $res = $this->withHeaders(bearer(userAccessToken($user)))->getJson('/v1/notifications?status=read')->assertOk();
    expect($res->json('data'))->toHaveCount(1)->and($res->json('data.0.id'))->toBe((int) $read->id);
});

it('requires authentication on all inbox routes', function () {
    $this->getJson('/v1/notifications')->assertStatus(401);
    $this->postJson('/v1/notifications/1/read')->assertStatus(401);
    $this->postJson('/v1/notifications/read-all')->assertStatus(401);
});
