<?php

use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;

/*
| A3 — an admin's free grant takes the row's tier (own access → main, family → additional) and is recorded,
| so it is not shown as a legacy comp. A9 — invitation limits are counted only for invitations really sent;
| a family head may invite the same person to a second gate. A11 — permissions no endpoint checks yet are
| marked `in_use: false`.
*/

beforeEach(fn () => Bus::fake());

function a3Complex(): array
{
    $complex = makeComplex('CX-A3', 'Kompleks A3');
    $gates = [];
    foreach (['SER-A3-1', 'SER-A3-2'] as $i => $serial) {
        $g = makeActiveDevice($serial, '+99470013000'.$i);
        $g->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complex->id, 'owner_user_id' => null])->save();
        $gates[] = $g;
    }

    return [$complex, $gates];
}

it('gives a resident\'s own row the main tier, a family row the additional one, and records the grant (A3)', function () {
    [$complex, [$gate]] = a3Complex();
    $resident = makeUser('+994501300001');
    app(ComplexMembershipService::class)->join($complex->id, $resident);
    makeDeviceUser($resident, $gate, 'user');
    $member = makeUser('+994501300002');
    $link = FamilyLink::query()->create(['head_user_id' => $resident->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    DeviceUser::query()->create(['device_id' => $gate->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active', 'family_link_id' => $link->id, 'added_by_user_id' => $resident->id]);
    $super = makeSuperAdmin('a3@salamhayetimiz.az');

    $this->actingAs($super, 'admin')->postJson("/admin/v1/devices/{$gate->id}/users/{$resident->id}/subscription", ['term_days' => 30])->assertSuccessful();
    $this->actingAs($super, 'admin')->postJson("/admin/v1/devices/{$gate->id}/users/{$member->id}/subscription", ['term_days' => 30])->assertSuccessful();

    $own = Subscription::query()->whereHas('deviceUser', fn ($q) => $q->where('user_id', $resident->id))->firstOrFail();
    $fam = Subscription::query()->whereHas('deviceUser', fn ($q) => $q->where('user_id', $member->id))->firstOrFail();
    expect($own->tier)->toBe(SubscriptionTier::Main)
        ->and($fam->tier)->toBe(SubscriptionTier::Additional)
        ->and((int) $own->comp_granted_by_admin_id)->toBe((int) $super->id);

    $this->actingAs($super, 'admin')->getJson("/admin/v1/subscriptions/{$own->id}")->assertOk()
        ->assertJsonPath('data.is_legacy_comp', false)
        ->assertJsonPath('data.comp_grant.by_admin_id', $super->id);

    // extending an existing subscription keeps its tier
    $this->actingAs($super, 'admin')->postJson("/admin/v1/devices/{$gate->id}/users/{$member->id}/subscription", ['term_days' => 10])->assertSuccessful();
    expect($fam->fresh()->tier)->toBe(SubscriptionTier::Additional);
});

it('lets a family head invite the same person to a second gate (A9)', function () {
    [, [$gate1, $gate2]] = a3Complex();
    $head = makeUser('+994501300011');
    $svc = app(InvitationService::class);

    $svc->createFamilyMember($head, 'qohum@example.test', 'Qohum', 'Bir', $gate1->id);
    $svc->createFamilyMember($head, 'qohum@example.test', 'Qohum', 'Bir', $gate2->id);

    expect(Invitation::query()->where('invitee_email', 'qohum@example.test')->where('status', 'pending')->count())->toBe(2);

    expect(fn () => $svc->createFamilyMember($head, 'qohum@example.test', 'Qohum', 'Bir', $gate1->id))
        ->toThrow(\App\Domain\Roster\Exceptions\InvitationException::class); // still one per gate
});

it('does not count an invitation resend that was refused (A9)', function () {
    config(['domain.invitations.inviter_max_per_day' => 1]);
    [$complex] = a3Complex();
    $manager = makeAdminRole('complex_manager', $complex->id);
    $svc = app(InvitationService::class);
    ['invitation' => $inv] = $svc->createComplexResident($complex->id, $manager, 'sakin@example.test', 'Sakin', 'Bir');
    $this->travel(2)->minutes(); // past the resend cooldown

    expect(fn () => $svc->resend($inv->fresh()))->toThrow(\App\Domain\Roster\Exceptions\InvitationException::class); // inviter limit
    expect(RateLimiter::attempts('invitation:send:inv:'.$inv->id))->toBe(0); // the per-invitation quota was not eaten
});

it('marks permissions that no endpoint checks yet (A11)', function () {
    seedRbac();
    $groups = $this->actingAs(makeSuperAdmin('a11@salamhayetimiz.az'), 'admin')->getJson('/admin/v1/access/permissions')->assertOk()->json('data');
    $flags = collect($groups)->flatMap(fn ($g) => $g['permissions'])->pluck('in_use', 'key');

    expect($flags['vehicles.view'])->toBeFalse()
        ->and($flags['apartments.manage'])->toBeFalse()
        ->and($flags['invoices.view'])->toBeFalse()
        ->and($flags['support.otp.resend'])->toBeFalse()
        ->and($flags['residents.view'])->toBeTrue();
});
