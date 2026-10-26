<?php

use App\Domain\Admin\Models\Permission;
use App\Domain\Admin\Models\UserPermission;

/*
| O9 — PATCH /admin/v1/admins/{id} with status=suspended|offboarded has the same guards as DELETE: an admin
| never blocks their own account, and the last active super_admin can never be blocked.
*/

it('does not let an admin suspend or offboard their own account', function (string $status) {
    $super = makeSuperAdmin('o9-self@salamhayetimiz.az');
    makeSuperAdmin('o9-other@salamhayetimiz.az'); // another super exists, so only the self-check applies

    $this->actingAs($super, 'admin')->patchJson("/admin/v1/admins/{$super->id}", ['status' => $status])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['status'], 'error.fields');

    expect($super->fresh()->status->value)->toBe('active');
})->with(['suspended', 'offboarded']);

it('never blocks the last active super_admin, even for an admin granted admins.update', function () {
    seedRbac();
    $super = makeSuperAdmin('o9-last@salamhayetimiz.az');
    $tech = makeAdminRole('technical');
    UserPermission::query()->create([
        'admin_user_id' => $tech->id,
        'permission_id' => (int) Permission::query()->where('key', 'admins.update')->value('id'),
        'effect' => 'grant',
    ]);

    $this->actingAs($tech, 'admin')->patchJson("/admin/v1/admins/{$super->id}", ['status' => 'suspended'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['admin'], 'error.fields');

    expect($super->fresh()->status->value)->toBe('active');
});

it('lets a super_admin suspend another super_admin while one active super remains', function () {
    $actor = makeSuperAdmin('o9-actor@salamhayetimiz.az');
    $target = makeSuperAdmin('o9-target@salamhayetimiz.az');

    $this->actingAs($actor, 'admin')->patchJson("/admin/v1/admins/{$target->id}", ['status' => 'suspended'])
        ->assertOk()
        ->assertJsonPath('status', 'suspended');

    // Offboarding the already-suspended super is still fine: the actor is another active super.
    $this->actingAs($actor, 'admin')->patchJson("/admin/v1/admins/{$target->id}", ['status' => 'offboarded'])
        ->assertOk()
        ->assertJsonPath('status', 'offboarded');
});

it('still lets an admin reactivate an account and edit their own name', function () {
    $actor = makeSuperAdmin('o9-reactivate@salamhayetimiz.az');
    $other = makeAdminRole('operator');
    $other->forceFill(['status' => 'suspended'])->save();

    $this->actingAs($actor, 'admin')->patchJson("/admin/v1/admins/{$other->id}", ['status' => 'active'])
        ->assertOk()->assertJsonPath('status', 'active');
    $this->actingAs($actor, 'admin')->patchJson("/admin/v1/admins/{$actor->id}", ['name' => 'Baş admin'])
        ->assertOk()->assertJsonPath('name', 'Baş admin');
});
