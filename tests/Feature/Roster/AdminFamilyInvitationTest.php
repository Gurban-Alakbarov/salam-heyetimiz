<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Roster\Models\Invitation;
use Illuminate\Support\Facades\Queue;

/*
| Owner decision 2026-10-29 (a): an admin invites a family member on behalf of the family head — same rules and the
| same invitation e-mail as the head's own invite in the app; audited as family.invited_by_admin.
*/

beforeEach(function () {
    seedRbac();
    Queue::fake();
});

it('invites a family member on behalf of the private-device owner', function () {
    $owner = makeUser('+994700810001');
    $owner->forceFill(['email' => 'head@example.com', 'email_verified_at' => now()])->save();
    $device = makeOwnedDevice($owner, 'FAM-ADM-1', '+994700810002');

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson("/admin/v1/devices/{$device->id}/family-invitations", [
        'head_user_id' => $owner->id, 'first_name' => 'Aysel', 'last_name' => 'Məmmədova', 'email' => 'aysel@example.com',
    ])->assertCreated()->assertJsonPath('data.granted', false);

    $invitation = Invitation::query()->where('invitee_email', 'aysel@example.com')->firstOrFail();
    expect((int) $invitation->invited_by_user_id)->toBe((int) $owner->id)
        ->and((int) $invitation->device_id)->toBe((int) $device->id)
        ->and(AuditLog::query()->where('action', 'family.invited_by_admin')->exists())->toBeTrue();
});

it('refuses when the chosen person is not the family head of this device', function () {
    $owner = makeUser('+994700810011');
    $device = makeOwnedDevice($owner, 'FAM-ADM-2', '+994700810012');
    $other = makeUser('+994700810013');
    makeDeviceUser($other, $device, 'user');

    $this->actingAs(makeSuperAdmin(), 'admin')->postJson("/admin/v1/devices/{$device->id}/family-invitations", [
        'head_user_id' => $other->id, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'x@example.com',
    ])->assertStatus(422)->assertJsonPath('error.code', 'not_family_head');
});

it('keeps a komendant inside their own complex', function () {
    $complex = makeComplex('CX-FAM', 'Fam Complex');
    $owner = makeUser('+994700810021');
    $device = makeOwnedDevice($owner, 'FAM-ADM-3', '+994700810022'); // not in the komendant's complex

    $this->actingAs(makeAdminRole('complex_manager', (int) $complex->id), 'admin')
        ->postJson("/admin/v1/devices/{$device->id}/family-invitations", [
            'head_user_id' => $owner->id, 'first_name' => 'A', 'last_name' => 'B', 'email' => 'y@example.com',
        ])->assertNotFound();
});
