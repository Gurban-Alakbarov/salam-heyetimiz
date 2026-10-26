<?php

use App\Domain\Users\Models\User;

/*
| Y6 — a complex-mode device has no owner by design: assigning or transferring an owner is refused (409
| device_is_complex_mode) before anyone is provisioned. Private devices are unaffected.
*/

beforeEach(function () {
    $this->super = makeSuperAdmin();
    $cx = makeComplex('CX-Y6', 'Kompleks Y6');
    $this->gate = makeUnassignedDevice('SER-Y6-1', '+994700660601');
    $this->gate->forceFill(['complex_id' => $cx->id, 'ownership_mode' => 'complex'])->save();
});

it('refuses to assign an owner to a complex device (admin and technical routes)', function () {
    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$this->gate->id}/assign", ['owner_phone' => '+994505660601'])
        ->assertStatus(409)->assertJsonPath('error.code', 'device_is_complex_mode');
    $this->actingAs(makeAdminRole('operator'), 'admin')->postJson("/v1/technical/devices/{$this->gate->id}/assign", ['owner_phone' => '+994505660601'])
        ->assertStatus(409)->assertJsonPath('error.code', 'device_is_complex_mode');

    expect($this->gate->fresh()->owner_user_id)->toBeNull()
        ->and(User::query()->where('phone', '+994505660601')->exists())->toBeFalse();
});

it('refuses to transfer a complex device to an owner', function () {
    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$this->gate->id}/transfer", [
        'new_owner_phone' => '+994505660602', 'reason' => 'satış',
    ])->assertStatus(409)->assertJsonPath('error.code', 'device_is_complex_mode');

    expect($this->gate->fresh()->owner_user_id)->toBeNull();
});

it('a private device is assigned as before', function () {
    $private = makeUnassignedDevice('SER-Y6-2', '+994700660602');

    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/devices/{$private->id}/assign", ['owner_phone' => '+994505660603'])->assertOk();
    expect($private->fresh()->owner_user_id)->not->toBeNull();
});
