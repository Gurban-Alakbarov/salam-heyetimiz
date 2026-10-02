<?php

use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Queries\ComplexDeviceQuery;
use App\Domain\Roster\Enums\ComplexMemberStatus;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Exceptions\DeviceNotAssignedException;
use App\Domain\Roster\Models\ComplexMember;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Enums\SuspensionReason;
use App\Domain\Subscriptions\Queries\SubscriptionStatusQuery;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\Gate;

/*
| IMPLEMENTATION_PLAN B4 — complex (ownerless, shared) devices + complex membership, with the private
| single-owner model left exactly as it was.
*/

function complexDevice(int $complexId, string $serial, string $sim, string $status = 'active'): Device
{
    $device = makeActiveDevice($serial, $sim);
    $device->forceFill(['ownership_mode' => 'complex', 'complex_id' => $complexId, 'owner_user_id' => null, 'status' => $status])->save();

    return $device->refresh();
}

function addRoster(Device $device, \App\Domain\Users\Models\User $user, DeviceUserRole $role = DeviceUserRole::User, ?int $familyLinkId = null)
{
    return app(RosterService::class)->addMember($device, $user, $role, ActorKind::Admin, null, $familyLinkId);
}

beforeEach(function () {
    $this->complex = makeComplex('CX-B4', 'Complex B4');
    $this->members = app(ComplexMembershipService::class);
});

// ---- Roster ----

it('private devices keep the owner requirement (unchanged)', function () {
    $unowned = makeActiveDevice('SER-B4-P1', '+994700040001');
    expect(fn () => addRoster($unowned, makeUser('+994500040001')))->toThrow(DeviceNotAssignedException::class);

    $owned = makeOwnedDevice(makeUser('+994500040002'), 'SER-B4-P2', '+994700040002');
    expect(addRoster($owned, makeUser('+994500040003'))->role)->toBe(DeviceUserRole::User);
});

it('complex devices accept user rows without an owner, but never an owner row or a complex-less device', function () {
    $device = complexDevice($this->complex->id, 'SER-B4-C1', '+994700040011');
    expect($device->ownership_mode)->toBe(DeviceOwnershipMode::Complex)->and($device->owner_user_id)->toBeNull();

    $row = addRoster($device, makeUser('+994500040011'), familyLinkId: 77);
    expect($row->role)->toBe(DeviceUserRole::User)->and((int) $row->family_link_id)->toBe(77);

    expect(fn () => addRoster($device, makeUser('+994500040012'), DeviceUserRole::Owner))->toThrow(DeviceNotAssignedException::class);

    $orphan = makeActiveDevice('SER-B4-C2', '+994700040012');
    $orphan->forceFill(['ownership_mode' => 'complex', 'complex_id' => null])->save();
    expect(fn () => addRoster($orphan->refresh(), makeUser('+994500040013')))->toThrow(DeviceNotAssignedException::class);
});

// ---- Access ----

it('complex access stands on each caller\'s own subscription only — no owner semantics', function () {
    $device = complexDevice($this->complex->id, 'SER-B4-S1', '+994700040021');
    $alice = makeUser('+994500040021');
    $bob = makeUser('+994500040022');
    $aliceRow = addRoster($device, $alice);
    $bobRow = addRoster($device, $bob);
    makeSubscription($bobRow); // Bob paid

    $query = app(SubscriptionStatusQuery::class);
    $alice0 = $query->for($alice->id, $device->id);
    expect($alice0->canOpen)->toBeFalse()->and($alice0->suspensionReason)->toBe(SuspensionReason::SubscriptionExpired); // not "others active"
    expect($query->for($bob->id, $device->id)->canOpen)->toBeTrue();

    makeSubscription($aliceRow, ['status' => SubscriptionStatus::PendingPayment]);
    expect($query->for($alice->id, $device->id)->canOpen)->toBeFalse(); // pending never opens
});

it('private access semantics are unchanged (owner expired + others active)', function () {
    $owner = makeUser('+994500040031');
    $device = makeOwnedDevice($owner, 'SER-B4-S2', '+994700040031');
    makeSubscription(addRoster($device, makeUser('+994500040032')));

    expect(app(SubscriptionStatusQuery::class)->for($owner->id, $device->id)->suspensionReason)
        ->toBe(SuspensionReason::OwnerSubExpiredOthersActive);
});

// ---- Membership ----

it('joins idempotently, removes with history, and re-joins with a new active row', function () {
    $user = makeUser('+994500040041');
    $first = $this->members->join($this->complex->id, $user);
    expect($this->members->join($this->complex->id, $user)->id)->toBe($first->id)
        ->and($this->members->isMember($this->complex->id, $user->id))->toBeTrue()
        ->and($this->members->complexIdsFor($user->id))->toBe([$this->complex->id]);

    expect($this->members->remove($this->complex->id, $user))->toBeTrue()
        ->and($first->fresh()->status)->toBe(ComplexMemberStatus::Removed)
        ->and($first->fresh()->removed_at)->not->toBeNull()
        ->and($this->members->isMember($this->complex->id, $user->id))->toBeFalse()
        ->and($this->members->remove($this->complex->id, $user))->toBeFalse();

    $again = $this->members->join($this->complex->id, $user);
    expect($again->id)->not->toBe($first->id)->and(ComplexMember::query()->count())->toBe(2);
});

// ---- Policies ----

it('complex residents can view complex devices; outsiders get 404', function () {
    $device = complexDevice($this->complex->id, 'SER-B4-V1', '+994700040051');
    $resident = makeUser('+994500040051');
    $this->members->join($this->complex->id, $resident);

    $this->actingAs($resident, 'user')->getJson("/v1/devices/{$device->id}")->assertOk()->assertJsonPath('can_open', false);
    $this->actingAs(makeUser('+994500040052'), 'user')->getJson("/v1/devices/{$device->id}")->assertNotFound();

    $other = makeComplex('CX-B4-OTHER', 'Other');
    $outsider = makeUser('+994500040053');
    $this->members->join($other->id, $outsider);
    $this->actingAs($outsider, 'user')->getJson("/v1/devices/{$device->id}")->assertNotFound();
});

it('subscribe: only complex residents, only active complex devices — never private devices', function () {
    $device = complexDevice($this->complex->id, 'SER-B4-SUB1', '+994700040061');
    $disabled = complexDevice($this->complex->id, 'SER-B4-SUB2', '+994700040062', 'disabled');
    $resident = makeUser('+994500040061');
    $this->members->join($this->complex->id, $resident);
    $owner = makeUser('+994500040062');
    $private = makeOwnedDevice($owner, 'SER-B4-SUB3', '+994700040063');

    expect(Gate::forUser($resident)->allows('subscribe', $device))->toBeTrue()
        ->and(Gate::forUser($resident)->allows('subscribe', $disabled))->toBeFalse()
        ->and(Gate::forUser(makeUser('+994500040063'))->allows('subscribe', $device))->toBeFalse()
        ->and(Gate::forUser($owner)->allows('subscribe', $private))->toBeFalse();
});

it('manageFamily: private owner, or a complex resident with OWN access — never a family member', function () {
    $owner = makeUser('+994500040071');
    $private = makeOwnedDevice($owner, 'SER-B4-F1', '+994700040071');
    $privateMember = makeUser('+994500040072');
    addRoster($private, $privateMember);

    $device = complexDevice($this->complex->id, 'SER-B4-F2', '+994700040072');
    $head = makeUser('+994500040073');
    $this->members->join($this->complex->id, $head);
    addRoster($device, $head);
    $family = makeUser('+994500040074');
    addRoster($device, $family, familyLinkId: 1);

    expect(Gate::forUser($owner)->allows('manageFamily', $private))->toBeTrue()
        ->and(Gate::forUser($privateMember)->allows('manageFamily', $private))->toBeFalse()
        ->and(Gate::forUser($head)->allows('manageFamily', $device))->toBeTrue()
        ->and(Gate::forUser($family)->allows('manageFamily', $device))->toBeFalse()
        ->and(Gate::forUser($family)->allows('view', $device))->toBeTrue()       // their granted device
        ->and(Gate::forUser($family)->allows('subscribe', $device))->toBeFalse(); // not a complex member
});

it('ComplexPolicy: residents of that complex, any admin, a complex_manager only for its own complex', function () {
    $resident = makeUser('+994500040081');
    $this->members->join($this->complex->id, $resident);
    $other = makeComplex('CX-B4-P2', 'Other 2');

    expect(Gate::forUser($resident)->allows('view', $this->complex))->toBeTrue()
        ->and(Gate::forUser($resident)->allows('view', $other))->toBeFalse()
        ->and(Gate::forUser(makeSuperAdmin('sa-b4@salam.test'))->allows('view', $other))->toBeTrue()
        ->and(Gate::forUser(makeAdminRole('complex_manager', $this->complex->id, 'cm-b4@salam.test'))->allows('view', $other))->toBeFalse()
        ->and(Gate::forUser(makeAdminRole('complex_manager', $this->complex->id, 'cm2-b4@salam.test'))->allows('view', $this->complex))->toBeTrue();
});

// ---- Read model ----

it('lists only the complex\'s active shared devices, with the caller\'s own subscription status', function () {
    $a = complexDevice($this->complex->id, 'SER-B4-Q1', '+994700040091');
    $b = complexDevice($this->complex->id, 'SER-B4-Q2', '+994700040092');
    $c = complexDevice($this->complex->id, 'SER-B4-Q3', '+994700040093');
    complexDevice($this->complex->id, 'SER-B4-Q4', '+994700040094', 'disabled');
    $privateInComplex = makeOwnedDevice(makeUser('+994500040091'), 'SER-B4-Q5', '+994700040095');
    $privateInComplex->forceFill(['complex_id' => $this->complex->id])->save();
    complexDevice(makeComplex('CX-B4-Q', 'Q')->id, 'SER-B4-Q6', '+994700040096');

    $me = makeUser('+994500040092');
    makeSubscription(addRoster($a, $me));
    makeSubscription(addRoster($b, $me), ['status' => SubscriptionStatus::PendingPayment]);
    makeSubscription(addRoster($c, makeUser('+994500040093'))); // someone else's — must not leak

    $rows = app(ComplexDeviceQuery::class)->forComplex($this->complex->id, $me->id)->keyBy('id');

    expect($rows->keys()->all())->toEqualCanonicalizing([$a->id, $b->id, $c->id])
        ->and($rows[$a->id]->caller_subscription_status)->toBe('active')
        ->and($rows[$b->id]->caller_subscription_status)->toBe('pending_payment')
        ->and($rows[$c->id]->caller_subscription_status)->toBe('none');
});
