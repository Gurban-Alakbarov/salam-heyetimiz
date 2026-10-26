<?php

use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Exceptions\WhitelistFullException;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Bus;

/*
| A10 — the whitelist capacity of the device model is enforced; the never-written "suspended" status: the
| user filter shows devices switched off by an admin. A5 — the visitor-link filter takes the `used_up`
| value shown on the rows. A2 — one online window (15 min) everywhere. A7 — a deactivated complex takes no
| new invitations, residents, subscriptions or payments; the admin "residents" stat counts members.
*/

it('refuses a roster add once the device whitelist is full (A10)', function () {
    $owner = makeUser('+994501101001');
    $device = makeOpenableDevice($owner, 'SER-A10-1', '+994700110001');
    DeviceModel::query()->whereKey($device->device_model_id)->update(['whitelist_capacity' => 2]);

    $super = makeSuperAdmin('a10@salamhayetimiz.az');
    $this->actingAs($super, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994501101002'])->assertStatus(201);
    $this->actingAs($super, 'admin')->postJson("/admin/v1/devices/{$device->id}/users", ['phone' => '+994501101003'])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'whitelist_full')
        ->assertJsonPath('error.details.capacity', 2);

    expect(fn () => app(RosterService::class)->addMember($device, makeUser('+994501101004'), DeviceUserRole::User, ActorKind::Admin, $super->id))
        ->toThrow(WhitelistFullException::class);
});

it('lists switched-off devices under the user "suspended" filter (A10)', function () {
    $owner = makeUser('+994501101011');
    $off = makeOpenableDevice($owner, 'SER-A10-2', '+994700110002');
    $off->forceFill(['status' => 'disabled'])->save();
    makeOpenableDevice($owner, 'SER-A10-3', '+994700110003');

    $ids = collect($this->actingAs($owner, 'user')->getJson('/v1/devices?filter=suspended')->assertOk()->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$off->id]);
});

it('filters visitor links by the used_up status the rows show (A5)', function () {
    $owner = makeUser('+994501101021');
    $device = makeOpenableDevice($owner, 'SER-A5-1', '+994700110021');
    [$used] = mintVisitorLink($device, $owner, VisitorAccessType::OneTime);
    $used->forceFill(['usage_count' => 1])->save();
    mintVisitorLink($device, $owner, VisitorAccessType::TimeLimited);

    $super = makeSuperAdmin('a5@salamhayetimiz.az');
    foreach (['used_up', 'used'] as $status) {
        $rows = $this->actingAs($super, 'admin')->getJson("/admin/v1/visitor-links?status={$status}")->assertOk()->json('data');
        expect(collect($rows)->pluck('id')->all())->toBe([$used->id]);
    }
    expect(collect($this->actingAs($owner, 'user')->getJson('/v1/visitor-links?status=used_up')->json('data'))->pluck('id')->all())->toBe([$used->id]);
});

it('reports online with the one shared 15-minute window (A2)', function () {
    $owner = makeUser('+994501101031');
    $device = makeOpenableDevice($owner, 'SER-A2-1', '+994700110031');

    $device->forceFill(['last_online_at' => now()->subMinutes(10)])->save();
    $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}")->assertJsonPath('online', true);

    $device->forceFill(['last_online_at' => now()->subMinutes(20)])->save();
    $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}")->assertJsonPath('online', false);
});

it('stops new invitations, residents, subscriptions and payments in a deactivated complex (A7)', function () {
    Bus::fake();
    $complex = makeComplex('CX-A7', 'Kompleks A7');
    $gate = makeActiveDevice('SER-A7-1', '+994700110041');
    $gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complex->id, 'owner_user_id' => null])->save();
    $resident = makeUser('+994501101041');
    app(ComplexMembershipService::class)->join($complex->id, $resident);
    $row = makeDeviceUser($resident, $gate, 'user');
    $sub = makeSubscription($row, ['ends_at' => now()->addDays(10)]);
    $manager = makeAdminRole('complex_manager', $complex->id);
    ['token' => $token] = app(InvitationService::class)->createComplexResident($complex->id, $manager, 'yeni@example.test', 'Yeni', 'Sakin');

    $complex->forceFill(['is_active' => false])->save();

    // already paid access keeps working
    expect(app(\App\Domain\Subscriptions\Queries\SubscriptionStatusQuery::class)->for($resident->id, $gate->id)->canOpen)->toBeTrue();

    expect(fn () => app(InvitationService::class)->createComplexResident($complex->id, $manager, 'diger@example.test', 'D', 'S'))
        ->toThrow(\App\Domain\Admin\Exceptions\ComplexInactiveException::class);

    $newcomer = makeUser('+994501101042');
    $newcomer->forceFill(['email' => 'yeni@example.test', 'email_verified_at' => now()])->save();
    $this->actingAs($newcomer, 'user')->postJson("/v1/invites/{$token}/accept")->assertStatus(422)->assertJsonPath('error.code', 'complex_inactive');

    $this->actingAs($resident, 'user')->postJson("/v1/complexes/{$complex->id}/devices/{$gate->id}/subscribe", [], ['Idempotency-Key' => 'a7-sub'])
        ->assertStatus(422)->assertJsonPath('error.code', 'complex_inactive');

    $this->actingAs($resident, 'user')->postJson('/v1/orders', [
        'purpose' => 'sub_renewal', 'items' => [['item_type' => 'sub_renewal', 'referenced_id' => $sub->id]],
    ], ['Idempotency-Key' => 'a7-renew'])->assertStatus(422)->assertJsonValidationErrors(['items'], 'error.fields');
});

it('counts complex residents the same way as the Komendant (A7)', function () {
    $complex = makeComplex('CX-A7-C', 'Say');
    $gate = makeActiveDevice('SER-A7-2', '+994700110051');
    $gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complex->id, 'owner_user_id' => null])->save();
    foreach (['+994501101051', '+994501101052'] as $phone) {
        app(ComplexMembershipService::class)->join($complex->id, makeUser($phone));
    }
    makeDeviceUser(makeUser('+994501101053'), $gate, 'user'); // on the gate but not a member

    $this->actingAs(makeSuperAdmin('a7c@salamhayetimiz.az'), 'admin')->getJson("/admin/v1/complexes/{$complex->id}")
        ->assertOk()->assertJsonPath('stats.residents', 2);
});
