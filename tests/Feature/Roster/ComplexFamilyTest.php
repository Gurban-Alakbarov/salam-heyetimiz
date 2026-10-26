<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\FamilyLink;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Enums\SubscriptionTier;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Subscriptions\Support\SubscriptionPaymentAuthorizer;
use App\Domain\Users\Models\User;
use Database\Seeders\Notifications\NotificationTemplatesSeeder;
use Illuminate\Support\Facades\Bus;

/*
| O7 — the Komendant manages the families in its complex: removes a family member, hands a family to another
| member (old head stays as a member by default, or leaves), and removing a head needs a decision about the
| family. No family link stays "active" without access. Same actions from the admin panel.
*/

beforeEach(function () {
    Bus::fake();
    (new NotificationTemplatesSeeder)->run();
    $this->cx = makeComplex('CX-O7', 'Kompleks O7');
    $this->gate = makeActiveDevice('SER-O7-1', '+994700070701');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();

    // the Komendant: a mobile user linked to the complex's manager
    $this->kUser = makeUser('+994500070700');
    $this->manager = makeAdminRole('complex_manager', $this->cx->id);
    $this->manager->forceFill(['user_id' => $this->kUser->id])->save();

    // a resident heading a family of two on the complex gate
    $this->head = makeUser('+994500070701');
    $this->head->forceFill(['full_name' => 'Başçı Bir'])->save();
    app(ComplexMembershipService::class)->join($this->cx->id, $this->head);
    $this->headRow = makeDeviceUser($this->head, $this->gate, 'user');
    makeSubscription($this->headRow);
    [$this->m1, $this->m1Row] = o7Member($this->gate, $this->head, '+994500070702', 'Üzv Bir');
    [$this->m2, $this->m2Row] = o7Member($this->gate, $this->head, '+994500070703', 'Üzv İki');
});

function o7Member($gate, User $head, string $phone, string $name): array
{
    $member = makeUser($phone);
    $member->forceFill(['full_name' => $name])->save();
    $link = FamilyLink::query()->create(['head_user_id' => $head->id, 'member_user_id' => $member->id, 'status' => 'active', 'linked_at' => now()]);
    $row = DeviceUser::query()->create([
        'device_id' => $gate->id, 'user_id' => $member->id, 'role' => 'user', 'status' => 'active',
        'family_link_id' => $link->id, 'added_by_user_id' => $head->id,
    ]);
    makeSubscription($row, ['tier' => SubscriptionTier::Additional]);

    return [$member, $row];
}

function o7Link(User $head, User $member): ?FamilyLink
{
    return FamilyLink::query()->where('head_user_id', $head->id)->where('member_user_id', $member->id)->where('status', 'active')->first();
}

it('lists each head with its family for the Komendant', function () {
    $res = $this->actingAs($this->kUser, 'user')->getJson('/v1/komendant/residents')->assertOk();

    $row = collect($res->json('data'))->firstWhere('user_id', $this->head->id);
    expect($row['is_family_head'])->toBeTrue()
        ->and(collect($row['family'])->pluck('user_id')->all())->toEqualCanonicalizing([$this->m1->id, $this->m2->id])
        ->and($row['family'][0]['active_subscriptions'])->toBe(1);
});

it('removes a family member from the complex — the link ends, the rest of the family stays', function () {
    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$this->m1->id}")
        ->assertOk()
        ->assertJsonPath('data.removed_as', 'family_member')
        ->assertJsonPath('data.revoked_rows', 1)
        ->assertJsonPath('data.cancelled_subscriptions', 1);

    expect($this->m1Row->fresh()->status->value)->toBe('revoked')
        ->and(o7Link($this->head, $this->m1))->toBeNull()
        ->and($this->m2Row->fresh()->status->value)->toBe('active')
        ->and(o7Link($this->head, $this->m2))->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'complex.family_member_removed')->count())->toBe(1);

    // the member and the head are told
    expect(Notification::query()->where('template_key', 'family.member_removed')->where('channel', 'inapp')->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->head->id, $this->m1->id])->sort()->values()->all());
});

it('asks what happens to the family before removing its head', function () {
    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$this->head->id}")
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'family_decision_required')
        ->assertJsonCount(2, 'error.details.members');

    expect($this->headRow->fresh()->status->value)->toBe('active');
});

it('removes a head with the whole family and ends every link', function () {
    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$this->head->id}", ['family_action' => 'remove_family'])
        ->assertOk()
        ->assertJsonPath('data.revoked_rows', 3);

    expect($this->m1Row->fresh()->status->value)->toBe('revoked')
        ->and($this->m2Row->fresh()->status->value)->toBe('revoked')
        ->and(FamilyLink::query()->where('head_user_id', $this->head->id)->where('status', 'active')->count())->toBe(0);
});

it('removes a head and hands the family to a member', function () {
    $this->actingAs($this->kUser, 'user')->deleteJson("/v1/komendant/residents/{$this->head->id}", [
        'family_action' => 'new_head', 'new_head_user_id' => $this->m1->id,
    ])->assertOk()
        ->assertJsonPath('data.new_head_user_id', $this->m1->id)
        ->assertJsonPath('data.old_head', 'removed');

    // the new head: own access, main tier, a resident of the complex now
    $m1Row = $this->m1Row->fresh();
    expect($m1Row->status->value)->toBe('active')
        ->and($m1Row->family_link_id)->toBeNull()
        ->and(Subscription::query()->where('device_user_id', $m1Row->id)->value('tier'))->toBe(SubscriptionTier::Main)
        ->and(ComplexMember::query()->where('complex_id', $this->cx->id)->where('user_id', $this->m1->id)->where('status', 'active')->exists())->toBeTrue();

    // the other member moved under the new head, access and paid days untouched; the new head may pay for them
    $m2Row = $this->m2Row->fresh();
    $m2Sub = Subscription::query()->where('device_user_id', $m2Row->id)->firstOrFail();
    expect($m2Row->status->value)->toBe('active')
        ->and((int) $m2Row->family_link_id)->toBe((int) o7Link($this->m1, $this->m2)->id)
        ->and($m2Sub->status->value)->toBe('active')
        ->and(app(SubscriptionPaymentAuthorizer::class)->canPay($this->m1->fresh(), $m2Sub))->toBeTrue()
        ->and(app(SubscriptionPaymentAuthorizer::class)->canPay($this->head->fresh(), $m2Sub))->toBeFalse();

    // the old head left; none of their links is left active
    expect($this->headRow->fresh()->status->value)->toBe('revoked')
        ->and(FamilyLink::query()->where('head_user_id', $this->head->id)->where('status', 'active')->count())->toBe(0);

    expect(Notification::query()->where('template_key', 'family.head_changed')->where('channel', 'inapp')->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->head->id, $this->m1->id, $this->m2->id])->sort()->values()->all());
});

it('changes the head and keeps the old head as a member by default', function () {
    $this->actingAs($this->kUser, 'user')->postJson("/v1/komendant/families/{$this->head->id}/head", ['new_head_user_id' => $this->m2->id])
        ->assertOk()
        ->assertJsonPath('data.old_head', 'member')
        ->assertJsonPath('data.members_moved', 1);

    $headRow = $this->headRow->fresh();
    expect($headRow->status->value)->toBe('active')
        ->and((int) $headRow->family_link_id)->toBe((int) o7Link($this->m2, $this->head)->id)
        ->and(Subscription::query()->where('device_user_id', $headRow->id)->value('tier'))->toBe(SubscriptionTier::Additional)
        ->and($this->m2Row->fresh()->family_link_id)->toBeNull()
        ->and((int) $this->m1Row->fresh()->family_link_id)->toBe((int) o7Link($this->m2, $this->m1)->id)
        ->and(AuditLog::query()->where('action', 'complex.family_head_changed')->count())->toBe(1);
});

it('refuses a new head from outside the family and a head that is not one', function () {
    $stranger = makeUser('+994500070709');

    $this->actingAs($this->kUser, 'user')->postJson("/v1/komendant/families/{$this->head->id}/head", ['new_head_user_id' => $stranger->id])
        ->assertStatus(422)->assertJsonPath('error.code', 'not_family_member');
    $this->actingAs($this->kUser, 'user')->postJson("/v1/komendant/families/{$this->m1->id}/head", ['new_head_user_id' => $this->m2->id])
        ->assertStatus(422)->assertJsonPath('error.code', 'not_family_head');
});

it('offers the same actions in the admin panel, scoped to the complex', function () {
    $members = $this->actingAs($this->manager, 'admin')->getJson("/admin/v1/complexes/{$this->cx->id}/members")->assertOk()->json('data');
    expect(collect($members)->firstWhere('user_id', $this->head->id)['family'])->toHaveCount(2);

    $other = makeComplex('CX-O7-B', 'Başqa');
    $this->actingAs($this->manager, 'admin')->deleteJson("/admin/v1/complexes/{$other->id}/members/{$this->m1->id}")->assertNotFound();

    $this->actingAs($this->manager, 'admin')->deleteJson("/admin/v1/complexes/{$this->cx->id}/members/{$this->m1->id}")
        ->assertOk()->assertJsonPath('data.removed_as', 'family_member');

    $super = makeSuperAdmin('o7-super@salamhayetimiz.az');
    $this->actingAs($super, 'admin')->postJson("/admin/v1/complexes/{$this->cx->id}/families/{$this->head->id}/head", [
        'new_head_user_id' => $this->m2->id, 'old_head' => 'remove',
    ])->assertOk()->assertJsonPath('data.old_head', 'removed');

    expect($this->m2Row->fresh()->family_link_id)->toBeNull()
        ->and($this->headRow->fresh()->status->value)->toBe('revoked');
});

it('makes a complex_manager\'s resident removal ask about the family too', function () {
    $this->actingAs($this->manager, 'admin')->deleteJson("/admin/v1/residents/{$this->head->id}")
        ->assertStatus(409)->assertJsonPath('error.code', 'family_decision_required');

    $this->actingAs($this->manager, 'admin')->deleteJson("/admin/v1/residents/{$this->head->id}", ['family_action' => 'remove_family'])
        ->assertOk()->assertJsonPath('data.removed_from', 'complex');
});
