<?php

use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;

/*
| A4 — the device list / detail tell the app whether the caller may create visitor links: a family member may
| open but never share (BR-19), and nobody shares access they do not hold.
*/

it('lets the owner share and keeps a family member from it', function () {
    $owner = makeUser('+994501040401');
    $device = makeOpenableDevice($owner, 'SER-A4-1', '+994700040401');
    $member = makeUser('+994501040402');
    $link = FamilyLink::query()->create(['head_user_id' => $owner->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    $row = DeviceUser::query()->create([
        'device_id' => $device->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active',
        'family_link_id' => $link->id, 'added_by_user_id' => $owner->id,
    ]);
    makeSubscription($row);

    $this->actingAs($owner, 'user')->getJson('/v1/devices')->assertOk()
        ->assertJsonPath('data.0.can_share_visitor_link', true)
        ->assertJsonPath('data.0.via_family', false);

    $this->actingAs($member, 'user')->getJson("/v1/devices/{$device->id}")->assertOk()
        ->assertJsonPath('can_open', true)
        ->assertJsonPath('via_family', true)
        ->assertJsonPath('can_share_visitor_link', false);
});

it('does not offer sharing when the caller cannot open', function () {
    $owner = makeUser('+994501040403');
    $device = makeOwnedDevice($owner, 'SER-A4-2', '+994700040403'); // no subscription

    $this->actingAs($owner, 'user')->getJson("/v1/devices/{$device->id}")->assertOk()
        ->assertJsonPath('can_open', false)
        ->assertJsonPath('can_share_visitor_link', false);
});
