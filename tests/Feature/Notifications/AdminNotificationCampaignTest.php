<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Notifications\Enums\CampaignStatus;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationCampaign;
use App\Domain\Users\Models\User;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;

/*
| Admin notification campaigns (batch 11): adminList / previewAudience / send / show. Send fans out a
| system_announcement (fixed push + inapp via the system.admin_campaign template) to a resident audience,
| deduped to distinct user_id and scoped for complex_manager. confirmed=true + Idempotency-Key required.
*/

beforeEach(function (): void {
    seedRbac();
    (new NotificationTemplatesSeeder)->run();
});

/** A resident (user + active device + owner roster row), optionally placed in a complex. */
function campaignResident(string $phone, string $serial, string $sim, ?int $complexId = null): User
{
    $user = makeUser($phone);
    $device = makeActiveDevice($serial, $sim);
    if ($complexId !== null) {
        $device->update(['complex_id' => $complexId]);
    }
    makeDeviceUser($user, $device, 'owner');

    return $user;
}

/** POST a campaign send as $admin with sane defaults; override body via $overrides, key via $idemKey. */
function sendCampaign(AdminUser $admin, array $overrides = [], ?string $idemKey = 'idem-1')
{
    $body = array_merge([
        'type' => 'system',
        'title' => 'Sistem elanı',
        'body' => 'Bütün sakinlərə bildiriş',
        'language' => 'az',
        'confirmed' => true,
        'audience' => ['scope' => 'all_users'],
    ], $overrides);

    $headers = $idemKey !== null ? ['Idempotency-Key' => $idemKey] : [];

    return test()->actingAs($admin, 'admin')->postJson('/admin/v1/notifications', $body, $headers);
}

it('lets a super_admin send a campaign (202) and fans out push + inapp to every recipient', function () {
    $u1 = campaignResident('+994552000001', 'CMP-1', '+994700800001');
    $u2 = campaignResident('+994552000002', 'CMP-2', '+994700800002');

    $res = sendCampaign(makeSuperAdmin())
        ->assertStatus(202)
        ->assertJsonPath('type', 'system')
        ->assertJsonPath('audience_scope', 'all_users')
        ->assertJsonPath('total_recipients', 2);

    $campaign = NotificationCampaign::query()->firstOrFail();
    expect($campaign->status)->toBe(CampaignStatus::Sent)   // sync queue → fan-out already ran
        ->and($campaign->confirmed_at)->not->toBeNull();

    foreach ([$u1, $u2] as $u) {
        $rows = Notification::query()->where('user_id', $u->id)->get();
        expect($rows->pluck('channel')->map->value->sort()->values()->all())->toBe(['inapp', 'push'])
            ->and($rows->first()->template_key)->toBe('system.admin_campaign')
            ->and($rows->first()->payload['type'])->toBe('system_announcement')
            ->and((int) $rows->first()->campaign_id)->toBe((int) $campaign->id)
            ->and($rows->first()->payload['title'])->toBe('Sistem elanı')
            ->and($rows->firstWhere('channel', 'push')->dedupe_key)->toBe('campaign:'.$campaign->id.':'.$u->id);
    }
});

it('lets an operator send (global RBAC)', function () {
    campaignResident('+994552000010', 'CMP-OP', '+994700800010');

    sendCampaign(makeAdminRole('operator'))->assertStatus(202);
    expect(NotificationCampaign::query()->count())->toBe(1);
});

it('scopes a complex_manager send to its own complex (intersection)', function () {
    $complexA = makeComplex('CX-A', 'A');
    $complexB = makeComplex('CX-B', 'B');
    $inA = campaignResident('+994552000020', 'CMP-A', '+994700800020', $complexA->id);
    $inB = campaignResident('+994552000021', 'CMP-B', '+994700800021', $complexB->id);

    $manager = makeAdminRole('complex_manager', $complexA->id);
    sendCampaign($manager, ['audience' => ['scope' => 'all_users']])
        ->assertStatus(202)
        ->assertJsonPath('total_recipients', 1); // only complex A

    expect(Notification::query()->where('user_id', $inA->id)->count())->toBe(2)
        ->and(Notification::query()->where('user_id', $inB->id)->count())->toBe(0);
});

it('lets support view but forbids send', function () {
    $support = makeAdminRole('support');

    test()->actingAs($support, 'admin')->getJson('/admin/v1/notifications')->assertOk();
    sendCampaign($support)->assertStatus(403);
});

it('forbids technical and finance from view and send', function (string $role) {
    $admin = makeAdminRole($role);

    test()->actingAs($admin, 'admin')->getJson('/admin/v1/notifications')->assertStatus(403);
    sendCampaign($admin)->assertStatus(403);
    expect(NotificationCampaign::query()->count())->toBe(0);
})->with(['technical', 'finance']);

it('previews the scoped recipient count without creating anything', function () {
    campaignResident('+994552000030', 'CMP-PV1', '+994700800030');
    campaignResident('+994552000031', 'CMP-PV2', '+994700800031');

    test()->actingAs(makeSuperAdmin(), 'admin')
        ->postJson('/admin/v1/notifications/audience/preview', ['audience' => ['scope' => 'all_users']])
        ->assertOk()
        ->assertJsonPath('recipient_count', 2);

    expect(NotificationCampaign::query()->count())->toBe(0)
        ->and(Notification::query()->count())->toBe(0);
});

it('rejects a send without confirmed=true (409)', function () {
    campaignResident('+994552000040', 'CMP-CF', '+994700800040');

    sendCampaign(makeSuperAdmin(), ['confirmed' => false])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'confirmation_required');

    expect(NotificationCampaign::query()->count())->toBe(0);
});

it('rejects a send without an Idempotency-Key (422)', function () {
    campaignResident('+994552000050', 'CMP-NK', '+994700800050');

    sendCampaign(makeSuperAdmin(), [], idemKey: null)->assertStatus(422);
    expect(NotificationCampaign::query()->count())->toBe(0);
});

it('replays the same campaign for a repeated Idempotency-Key + body (no duplicate fan-out)', function () {
    $u = campaignResident('+994552000060', 'CMP-ID', '+994700800060');
    $admin = makeSuperAdmin();

    $first = sendCampaign($admin, [], 'dup-key')->assertStatus(202)->json('id');
    $second = sendCampaign($admin, [], 'dup-key')->assertStatus(202)->json('id');

    expect($second)->toBe($first)
        ->and(NotificationCampaign::query()->count())->toBe(1)
        ->and(Notification::query()->where('user_id', $u->id)->count())->toBe(2); // push + inapp, not doubled
});

it('rejects a reused Idempotency-Key with a changed body (409 mismatch)', function () {
    campaignResident('+994552000070', 'CMP-MM', '+994700800070');
    $admin = makeSuperAdmin();

    sendCampaign($admin, ['title' => 'Birinci'], 'mm-key')->assertStatus(202);
    sendCampaign($admin, ['title' => 'İkinci'], 'mm-key')
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'idempotency_mismatch');

    expect(NotificationCampaign::query()->count())->toBe(1);
});

it('dedupes a user on two devices to a single recipient', function () {
    $user = makeUser('+994552000080');
    $d1 = makeActiveDevice('CMP-DD1', '+994700800080');
    $d2 = makeActiveDevice('CMP-DD2', '+994700800081');
    makeDeviceUser($user, $d1, 'owner');
    makeDeviceUser($user, $d2, 'user');

    sendCampaign(makeSuperAdmin())
        ->assertStatus(202)
        ->assertJsonPath('total_recipients', 1);

    expect(Notification::query()->where('user_id', $user->id)->count())->toBe(2); // one push + one inapp
});

it('resolves the user_ids audience scope to the listed residents', function () {
    $u1 = campaignResident('+994552000090', 'CMP-UI1', '+994700800090');
    $u2 = campaignResident('+994552000091', 'CMP-UI2', '+994700800091');
    campaignResident('+994552000092', 'CMP-UI3', '+994700800092'); // not targeted

    sendCampaign(makeSuperAdmin(), ['audience' => ['scope' => 'user_ids', 'user_ids' => [$u1->id, $u2->id]]])
        ->assertStatus(202)
        ->assertJsonPath('total_recipients', 2);

    expect(Notification::query()->distinct()->count('user_id'))->toBe(2);
});

it('scopes campaign listing and detail to the complex_manager that created them', function () {
    $complexA = makeComplex('CX-LA', 'LA');
    campaignResident('+994552000100', 'CMP-LA', '+994700800100', $complexA->id);
    $manager = makeAdminRole('complex_manager', $complexA->id);
    $super = makeSuperAdmin();

    // super creates a global campaign; the manager creates its own.
    $globalId = sendCampaign($super, [], 'g-key')->json('id');
    $ownId = sendCampaign($manager, ['audience' => ['scope' => 'all_users']], 'm-key')->json('id');

    // manager sees only its own in the list, and 404s on the global one.
    $list = test()->actingAs($manager, 'admin')->getJson('/admin/v1/notifications')->assertOk()->json('data');
    expect(collect($list)->pluck('id')->all())->toBe([$ownId]);

    test()->actingAs($manager, 'admin')->getJson('/admin/v1/notifications/'.$globalId)->assertStatus(404);
    test()->actingAs($manager, 'admin')->getJson('/admin/v1/notifications/'.$ownId)->assertOk();
    test()->actingAs($super, 'admin')->getJson('/admin/v1/notifications/'.$globalId)->assertOk();
});

it('rejects an unauthenticated caller', function () {
    test()->getJson('/admin/v1/notifications')->assertStatus(401);
    test()->postJson('/admin/v1/notifications/audience/preview', ['audience' => ['scope' => 'all_users']])->assertStatus(401);
});
