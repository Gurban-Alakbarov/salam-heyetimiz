<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Actions\DeleteResident;
use App\Domain\Roster\Actions\RemoveComplexResident;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Subscriptions\Enums\SubscriptionStatus;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use App\Domain\Visitor\Enums\VisitorAccessType;
use App\Domain\Visitor\Models\VisitorLink;
use Illuminate\Support\Facades\Queue;

/*
| K7 — a resident's visitor link is an extension of THEIR access. The moment the creator can no longer open the
| device (Komendant removal, account deleted, subscription ended, account blocked, roster revoked by a transfer)
| the link stops opening and reports as revoked. Removals that revoke the roster also revoke the creator's live
| links on that device right away (visible in lists). Admin-created links are not tied to any resident.
*/

beforeEach(function () {
    Queue::fake();
    $this->cx = makeComplex('CX-K7', 'Kompleks K7');
    $this->gate = makeActiveDevice('SER-K7-1', '+994700770001');
    $this->gate->forceFill(['ownership_mode' => 'complex', 'complex_id' => $this->cx->id, 'owner_user_id' => null])->save();
    $this->resident = makeUser('+994505770001');
    app(ComplexMembershipService::class)->join($this->cx->id, $this->resident);
    makeSubscription(makeDeviceUser($this->resident, $this->gate, 'user'));
});

function k7Link(Device $device, User $creator): array
{
    return mintVisitorLink($device, $creator, VisitorAccessType::TimeLimited, 720, 'Kuryer');
}

it('a live link of a resident with access opens (baseline)', function () {
    [, $token] = k7Link($this->gate, $this->resident);

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(202);
});

it('Komendant removal: the link stops opening at once and is revoked in the lists', function () {
    [$link, $token] = k7Link($this->gate, $this->resident);
    [$expired] = k7Link($this->gate, $this->resident);
    $expired->forceFill(['expires_at' => now()->subMinute()])->save();

    app(RemoveComplexResident::class)->handle($this->cx->id, $this->resident, makeUser('+994505770099'), makeAdminRole('complex_manager', $this->cx->id));

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(403)->assertJsonPath('error.code', 'visitor_link_revoked');
    expect($link->fresh()->revoked_at)->not->toBeNull()
        ->and($link->fresh()->revoked_by_user_id)->toBeNull()
        ->and($link->fresh()->revoked_by_admin_id)->toBeNull()
        ->and($expired->fresh()->revoked_at)->toBeNull(); // already-dead links are left as they are

    $audit = AuditLog::query()->where('action', 'visitor_link.revoked_on_access_removal')->firstOrFail();
    expect($audit->payload['visitor_link_ids'])->toBe([(int) $link->id]);
});

it('account deletion: the link is revoked', function () {
    [$link, $token] = k7Link($this->gate, $this->resident);

    app(DeleteResident::class)->handle($this->resident, makeSuperAdmin());

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(403)->assertJsonPath('error.code', 'visitor_link_revoked');
    expect($link->fresh()->revoked_at)->not->toBeNull();
});

it('subscription ended: the link no longer opens (no roster event, checked on every attempt)', function () {
    [$link, $token] = k7Link($this->gate, $this->resident);
    Subscription::query()->update(['status' => SubscriptionStatus::Expired->value, 'ends_at' => now()->subMinute()]);

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(403)->assertJsonPath('error.code', 'visitor_link_revoked');
    $this->getJson("/v1/visit/{$token}")->assertOk()->assertJsonPath('status', 'revoked');
    expect($link->fresh()->revoked_at)->toBeNull();

    // renewing brings the link back while it is still within its window
    Subscription::query()->update(['status' => SubscriptionStatus::Active->value, 'ends_at' => now()->addDays(30)]);
    $this->postJson("/v1/visit/{$token}/open")->assertStatus(202);
});

it('a blocked creator account cannot let guests in', function () {
    [, $token] = k7Link($this->gate, $this->resident);
    $this->resident->forceFill(['status' => 'blocked'])->save();

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(403)->assertJsonPath('error.code', 'visitor_link_revoked');
});

it('a roster revoked without an event (ownership transfer) is still caught on open', function () {
    [, $token] = k7Link($this->gate, $this->resident);
    DeviceUser::query()->where('user_id', $this->resident->id)->update(['status' => 'revoked', 'revoked_at' => now()]);

    $this->postJson("/v1/visit/{$token}/open")->assertStatus(403)->assertJsonPath('error.code', 'visitor_link_revoked');
});

it('another resident losing access leaves this resident\'s and the admin\'s links working', function () {
    $neighbour = makeUser('+994505770002');
    app(ComplexMembershipService::class)->join($this->cx->id, $neighbour);
    makeSubscription(makeDeviceUser($neighbour, $this->gate, 'user'));

    [, $mine] = k7Link($this->gate, $this->resident);
    [, $admins] = mintVisitorLink($this->gate, makeSuperAdmin(), VisitorAccessType::TimeLimited, 60, 'Usta');

    app(RemoveComplexResident::class)->handle($this->cx->id, $neighbour, makeUser('+994505770098'), makeAdminRole('complex_manager', $this->cx->id));

    $this->postJson("/v1/visit/{$mine}/open")->assertStatus(202);
    $this->postJson("/v1/visit/{$admins}/open")->assertStatus(202);
    expect(VisitorLink::query()->whereNotNull('revoked_at')->count())->toBe(0);
});
