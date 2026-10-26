<?php

use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\InvitationService;
use Illuminate\Support\Facades\Bus;

/*
| O6 — resending an EXPIRED invitation while a newer one to the same email is already live used to break the
| one-live-invite unique index (500). Now: refused with 409 invitation_already_pending (the live one is the one to
| send); a stale pending sibling (past expiry, not swept yet) is expired first and does not block the resend.
*/

beforeEach(function () {
    Bus::fake([SendInvitationEmailJob::class]);
    $this->svc = app(InvitationService::class);
    $this->cx = makeComplex('CX-O6', 'Kompleks O6');
    $this->komendant = makeAdminRole('complex_manager', $this->cx->id);
});

function o6Invite(string $email = 'qonaq@example.az'): Invitation
{
    return test()->svc->createComplexResident(test()->cx->id, test()->komendant, $email, 'Anar', 'Əliyev')['invitation'];
}

function o6Expire(Invitation $invitation): void
{
    $invitation->forceFill(['status' => InvitationStatus::Expired->value, 'expires_at' => now()->subDay(), 'last_sent_at' => now()->subDays(8)])->save();
}

it('refuses (409, not 500) to revive an expired invite while a newer live one exists', function () {
    $old = o6Invite();
    o6Expire($old);
    $this->travel(2)->minutes();
    $new = o6Invite();

    try {
        $this->svc->resend($old->fresh());
        $this->fail('expected a refusal');
    } catch (InvitationException $e) {
        expect($e->errorCode)->toBe('invitation_already_pending')->and($e->status)->toBe(409);
    }

    expect($old->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($new->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('an expired invite with no live sibling is resent as before', function () {
    $old = o6Invite();
    o6Expire($old);

    $token = $this->svc->resend($old->fresh());

    expect($token)->not->toBe('')->and($old->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('a stale pending sibling (past expiry, not swept) does not block the resend; it is expired', function () {
    $old = o6Invite();
    o6Expire($old);
    $this->travel(2)->minutes();
    $stale = o6Invite();
    $stale->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->svc->resend($old->fresh());

    expect($old->fresh()->status)->toBe(InvitationStatus::Pending)
        ->and($stale->fresh()->status)->toBe(InvitationStatus::Expired);
});

it('over the API the Komendant gets a clean 409 too', function () {
    $old = o6Invite('api@example.az');
    o6Expire($old);
    $this->travel(2)->minutes();
    o6Invite('api@example.az');

    $user = makeUser('+994505660699');
    $user->forceFill(['email' => 'komendant-o6@example.az', 'email_verified_at' => now()])->save();
    $this->komendant->forceFill(['user_id' => $user->id])->save();

    $this->withHeaders(bearer(userAccessToken($user)))->postJson("/v1/komendant/invitations/{$old->id}/resend")
        ->assertStatus(409)->assertJsonPath('error.code', 'invitation_already_pending');
});
