<?php

use App\Domain\Admin\Models\Complex;
use App\Domain\Applications\Jobs\SendApplicationRejectedEmailJob;
use App\Domain\Applications\Models\LegalEntityApplication;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;

/*
| Y8 — one company (VÖEN) may run several complexes, but the SAME complex is never approved twice: a new
| application with the VÖEN + name (case / quotes / spacing ignored) of an approved complex is refused on submit,
| on edit and again on approval (an older pending duplicate). A different complex of the same VÖEN is welcome.
*/

beforeEach(function () {
    Bus::fake([SendApplicationRejectedEmailJob::class]);
    $this->super = makeSuperAdmin();
});

function y8User(string $phone): User
{
    $u = makeUser($phone);
    $u->forceFill(['email' => 'y8'.substr($phone, -4).'@example.test', 'email_verified_at' => now(), 'account_type' => 'legal'])->save();

    return $u->refresh();
}

function y8Legal(array $over = []): array
{
    return $over + ['complex_name' => 'Gənclik Park', 'legal_name' => 'Gənclik Park MMC', 'voen' => '1234567890',
        'legal_address' => 'Bakı, Atatürk pr. 1', 'contact_person_name' => 'Leyla Quliyeva', 'contact_phone' => '+994551112233',
        'contact_email' => 'leyla@example.test', 'address' => 'Bakı, Atatürk pr. 1A', 'latitude' => 40.4012345, 'longitude' => 49.8512345];
}

function y8SubmitAndApprove(User $user, array $data): int
{
    $id = test()->actingAs($user, 'user')->postJson('/v1/applications/legal', $data)->assertCreated()->json('data.id');
    test()->actingAs(test()->super, 'admin')->postJson("/admin/v1/applications/legal/{$id}/approve")->assertOk();

    return (int) $id;
}

it('refuses the same complex of the same VÖEN again, whatever the spelling; another complex is fine', function () {
    $company = y8User('+994505880001');
    y8SubmitAndApprove($company, y8Legal());

    $this->actingAs($company, 'user')->postJson('/v1/applications/legal', y8Legal(['complex_name' => '  «GƏNCLİK  PARK» ']))
        ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_legal_application');
    $this->actingAs(y8User('+994505880002'), 'user')->postJson('/v1/applications/legal', y8Legal())
        ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_legal_application');

    y8SubmitAndApprove($company, y8Legal(['complex_name' => 'Gənclik Park 2']));
    expect(Complex::query()->count())->toBe(2);
});

it('an edit cannot turn a pending application into a duplicate', function () {
    $company = y8User('+994505880003');
    y8SubmitAndApprove($company, y8Legal());
    $id = $this->actingAs($company, 'user')->postJson('/v1/applications/legal', y8Legal(['complex_name' => 'Başqa Kompleks']))->assertCreated()->json('data.id');

    $this->actingAs($company, 'user')->putJson("/v1/applications/legal/{$id}", y8Legal())
        ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_legal_application');
});

it('an older pending duplicate is refused on approval and creates no second complex', function () {
    $company = y8User('+994505880004');
    $first = y8SubmitAndApprove($company, y8Legal());
    $pending = LegalEntityApplication::query()->findOrFail($first)->replicate(['status', 'complex_id', 'reviewed_by_admin_id', 'reviewed_at']);
    $pending->forceFill(['status' => 'pending'])->save(); // submitted before the rule existed

    $this->actingAs($this->super, 'admin')->postJson("/admin/v1/applications/legal/{$pending->id}/approve")
        ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_legal_application');
    expect(Complex::query()->count())->toBe(1)->and($pending->fresh()->status->value)->toBe('pending');
});

it('a renamed complex still counts as the same complex', function () {
    $company = y8User('+994505880005');
    $id = y8SubmitAndApprove($company, y8Legal());
    Complex::query()->where('legal_entity_application_id', $id)->update(['name' => 'Yeni Ad Residence']);

    $this->actingAs($company, 'user')->postJson('/v1/applications/legal', y8Legal(['complex_name' => 'Yeni Ad Residence']))
        ->assertStatus(409)->assertJsonPath('error.code', 'duplicate_legal_application');
});
