<?php

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;

/*
| K4 — a complex_manager is NEVER global. Unbound from its complex (unassign, or complex_id cleared) it is scoped
| to no complex at all (AdminUser::NO_COMPLEX_SCOPE): every scoped list is empty and every scoped record 404/403.
| Making an admin a complex_manager needs a complex, and only a complex_manager can be a complex's manager.
| Global roles are untouched.
*/

beforeEach(function () {
    $this->super = makeSuperAdmin();
    $this->cx = makeComplex('CX-K4', 'Kompleks K4');
    $this->other = makeComplex('CX-K4-O', 'Başqa');

    $this->inside = makeActiveDevice('SER-K4-1', '+994700440001');
    $this->inside->forceFill(['complex_id' => $this->cx->id])->save();
    $this->elsewhere = makeActiveDevice('SER-K4-2', '+994700440002');
    $this->elsewhere->forceFill(['complex_id' => $this->other->id])->save();
    $this->loose = makeOwnedDevice(makeUser('+994500440003'), 'SER-K4-3', '+994700440003'); // no complex

    makeDeviceUser(makeUser('+994500440001'), $this->inside, 'user');
    makeDeviceUser(makeUser('+994500440002'), $this->elsewhere, 'user');

    $this->manager = makeAdminRole('complex_manager', $this->cx->id);
});

/** @return array<int, int> ids in a list response */
function k4Ids(AdminUser $admin, string $uri, string $key = 'id'): array
{
    return collect(test()->actingAs($admin, 'admin')->getJson($uri)->assertOk()->json('data'))->pluck($key)->all();
}

function k4Unbound(AdminUser $manager, Complex $cx): AdminUser
{
    test()->actingAs(makeSuperAdmin('super2-'.uniqid().'@salamhayetimiz.az'), 'admin')
        ->deleteJson("/admin/v1/complexes/{$cx->id}/managers/{$manager->id}")->assertNoContent();

    return $manager->refresh();
}

it('a bound complex_manager sees only its complex', function () {
    expect(k4Ids($this->manager, '/admin/v1/devices'))->toBe([$this->inside->id])
        ->and(k4Ids($this->manager, '/admin/v1/complexes'))->toBe([$this->cx->id]);
});

it('an UNBOUND complex_manager sees nothing — never every complex', function () {
    $manager = k4Unbound($this->manager, $this->cx);
    expect($manager->complex_id)->toBeNull()
        ->and($manager->complexScopeId())->toBe(AdminUser::NO_COMPLEX_SCOPE);

    expect(k4Ids($manager, '/admin/v1/devices'))->toBe([])
        ->and(k4Ids($manager, '/admin/v1/complexes'))->toBe([])
        ->and(k4Ids($manager, '/admin/v1/residents', 'user_id'))->toBe([])
        ->and(k4Ids($manager, '/admin/v1/visitor-links'))->toBe([]);

    $as = $this->actingAs($manager, 'admin');
    $as->getJson("/admin/v1/devices/{$this->inside->id}")->assertNotFound();
    $as->getJson("/admin/v1/devices/{$this->elsewhere->id}")->assertNotFound();
    $as->getJson("/admin/v1/devices/{$this->loose->id}")->assertNotFound(); // a device in no complex is not "in scope"
    $as->getJson("/admin/v1/complexes/{$this->cx->id}")->assertNotFound();
});

it('clearing complex_id by PATCH also leaves the manager with nothing, not everything', function () {
    $this->actingAs($this->super, 'admin')->patchJson("/admin/v1/admins/{$this->manager->id}", ['complex_id' => null])->assertOk();

    expect(k4Ids($this->manager->refresh(), '/admin/v1/devices'))->toBe([]);
});

it('making an admin a complex_manager needs a complex (as on create)', function () {
    $operator = makeAdminRole('operator');
    $as = $this->actingAs($this->super, 'admin');

    $as->patchJson("/admin/v1/admins/{$operator->id}", ['role' => 'complex_manager'])
        ->assertStatus(422)->assertJsonValidationErrors(['complex_id'], 'error.fields');
    expect($operator->refresh()->role->value)->toBe('operator');

    $as->patchJson("/admin/v1/admins/{$operator->id}", ['role' => 'complex_manager', 'complex_id' => null])
        ->assertStatus(422)->assertJsonValidationErrors(['complex_id'], 'error.fields');

    $as->patchJson("/admin/v1/admins/{$operator->id}", ['role' => 'complex_manager', 'complex_id' => $this->other->id])->assertOk();
    expect(k4Ids($operator->refresh(), '/admin/v1/devices'))->toBe([$this->elsewhere->id]);

    // a manager that already has a complex may re-send its role without repeating the complex
    $this->actingAs($this->super, 'admin')
        ->patchJson("/admin/v1/admins/{$this->manager->id}", ['role' => 'complex_manager', 'name' => 'Yeni ad'])->assertOk();
});

it('only a complex_manager can be assigned as a complex manager', function () {
    $operator = makeAdminRole('operator');
    $as = $this->actingAs($this->super, 'admin');

    $as->postJson("/admin/v1/complexes/{$this->cx->id}/managers", ['admin_id' => $operator->id])
        ->assertStatus(422)->assertJsonPath('error.code', 'not_complex_manager');
    expect($operator->refresh()->complex_id)->toBeNull();

    $unbound = k4Unbound($this->manager, $this->cx);
    $as->postJson("/admin/v1/complexes/{$this->other->id}/managers", ['admin_id' => $unbound->id])->assertOk();
    expect(k4Ids($unbound->refresh(), '/admin/v1/devices'))->toBe([$this->elsewhere->id]);
});

it('global roles keep their global scope', function () {
    $operator = makeAdminRole('operator');
    expect($operator->complexScopeId())->toBeNull()
        ->and(k4Ids($operator, '/admin/v1/devices'))->toEqualCanonicalizing([$this->inside->id, $this->elsewhere->id, $this->loose->id]);
});
