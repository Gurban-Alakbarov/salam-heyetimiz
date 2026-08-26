<?php

use App\Domain\Users\Models\User;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Models\VisitorLinkUsage;
use Illuminate\Support\Carbon;

/*
| GET /v1/visitor-links — the resident "Dəvətlərim / My invitations" listing. Ownership is enforced
| server-side (created_by_user_id — never in the client); status is the derived active/used/expired/
| revoked filter that mirrors VisitorLink::statusLabel(). Also covers the Home-card active count on /v1/me.
*/

it('requires authentication', function () {
    $this->getJson('/v1/visitor-links')->assertStatus(401);
});

it('returns an empty list when the resident has never created an invitation', function () {
    $owner = makeUser('+994551000001');
    makeOpenableDevice($owner, 'MI-EMPTY', '+994700100001'); // a device, but no links

    $res = $this->actingAs($owner, 'user')->getJson('/v1/visitor-links')->assertOk();

    expect($res->json('data'))->toBe([])
        ->and($res->json('page.has_more'))->toBeFalse();
});

it('lists only the caller-owned invitations, never another user\'s', function () {
    $owner = makeUser('+994551000002');
    $other = makeUser('+994551000003');
    $device = makeOpenableDevice($owner, 'MI-OWN', '+994700100002');
    makeDeviceUser($other, $device, 'user');

    [$mine] = mintVisitorLink($device, $owner, VisitorAccessType::OneTime);
    [$theirs] = mintVisitorLink($device, $other, VisitorAccessType::OneTime);

    $res = $this->actingAs($owner, 'user')->getJson('/v1/visitor-links')->assertOk();
    $ids = collect($res->json('data'))->pluck('id')->all();

    expect($ids)->toBe([$mine->id])
        ->and($ids)->not->toContain($theirs->id)
        ->and($res->json('data.0'))->not->toHaveKey('token')
        ->and($res->json('data.0'))->not->toHaveKey('token_hash');
});

it('filters by derived status (active/used/expired/revoked) server-side', function () {
    $owner = makeUser('+994551000004');
    $device = makeOpenableDevice($owner, 'MI-STAT', '+994700100004');

    [$active] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60);

    [$used] = mintVisitorLink($device, $owner, VisitorAccessType::OneTime, 60);
    $used->update(['usage_count' => 1]); // one_time exhausted → used_up

    [$expired] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60);
    $expired->update(['expires_at' => now()->subHour()]);

    [$revoked] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60);
    $revoked->update(['revoked_at' => now()]);

    $ids = fn (string $s) => collect(
        $this->actingAs($owner, 'user')->getJson('/v1/visitor-links?status='.$s)->assertOk()->json('data')
    )->pluck('id')->all();

    expect($ids('active'))->toBe([$active->id])
        ->and($ids('used'))->toBe([$used->id])
        ->and($ids('expired'))->toBe([$expired->id])
        ->and($ids('revoked'))->toBe([$revoked->id]);

    // No filter → every link, newest first.
    $all = $this->actingAs($owner, 'user')->getJson('/v1/visitor-links')->assertOk();
    expect(collect($all->json('data'))->pluck('id')->all())
        ->toBe([$revoked->id, $expired->id, $used->id, $active->id]);
});

it('exposes real usage info (first_used_at from the usage log, never fabricated)', function () {
    $owner = makeUser('+994551000005');
    $device = makeOpenableDevice($owner, 'MI-USE', '+994700100005');
    [$link] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 120);

    // Two recorded uses — first_used_at must resolve to the EARLIER one.
    VisitorLinkUsage::query()->create(['visitor_link_id' => $link->id, 'used_at' => now()->subMinutes(30), 'result' => 'accepted']);
    VisitorLinkUsage::query()->create(['visitor_link_id' => $link->id, 'used_at' => now()->subMinutes(5), 'result' => 'accepted']);
    $link->update(['usage_count' => 2, 'last_used_at' => now()->subMinutes(5)]);

    $row = $this->actingAs($owner, 'user')->getJson('/v1/visitor-links')->assertOk()->json('data.0');

    expect($row['usage_count'])->toBe(2)
        ->and($row['first_used_at'])->not->toBeNull()
        ->and($row['last_used_at'])->not->toBeNull()
        ->and(Carbon::parse($row['first_used_at'])->lessThan(Carbon::parse($row['last_used_at'])))->toBeTrue();
});

it('surfaces the active invitation count on /v1/me for the Home card', function () {
    // /v1/me serialises the user (UserSelfResource), so it needs a fully-hydrated row — mirror the
    // Bootstrap tests' verified-user setup (->refresh() loads every column under strict attribute access).
    $owner = User::query()->create([
        'full_name' => 'Anar Test',
        'phone' => '+994551000006',
        'phone_country' => 'AZ',
        'email' => 'mi-me@test.az',
        'email_verified_at' => now(),
        'preferred_language' => 'az',
        'status' => 'active',
    ])->refresh();
    $device = makeOpenableDevice($owner, 'MI-ME', '+994700100006');

    mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60); // active
    [$revoked] = mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited, 60);
    $revoked->update(['revoked_at' => now()]); // not counted

    $me = $this->actingAs($owner, 'user')->getJson('/v1/me')->assertOk();

    expect($me->json('data.active_invitations_count'))->toBe(1);
});
