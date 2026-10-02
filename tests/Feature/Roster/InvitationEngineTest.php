<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Roster\Exceptions\InvitationException;
use App\Domain\Roster\Jobs\ExpireInvitationsJob;
use App\Domain\Roster\Jobs\SendInvitationEmailJob;
use App\Domain\Roster\Models\Invitation;
use App\Domain\Roster\Services\InvitationService;
use App\Domain\Roster\Support\InvitationTokens;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/*
| IMPLEMENTATION_PLAN B3 — the single (generalised) invitation engine: complex_resident + family_member.
*/

beforeEach(function () {
    Bus::fake([SendInvitationEmailJob::class]);
    $this->svc = app(InvitationService::class);
    $this->complex = makeComplex('CX-INV', 'Salam Həyət Test');
    $this->komendant = makeAdminRole('complex_manager', $this->complex->id, 'komendant-inv@salam.test');
});

function inviteResident(string $email = 'Resident@Example.AZ'): array
{
    return test()->svc->createComplexResident(test()->complex->id, test()->komendant, $email, ' Anar ', 'Əliyev');
}

/** Captures what TemplatedMailer hands to the SMTP layer. */
function fakeRuntimeMailer(bool $configured = true): object
{
    $mailer = new class(app(SettingsService::class), $configured) extends RuntimeMailer
    {
        public array $sent = [];

        public function __construct(SettingsService $settings, private bool $configured)
        {
            parent::__construct($settings);
        }

        public function isConfigured(): bool
        {
            return $this->configured;
        }

        public function send(string $to, string $subject, string $body, ?string $html = null): void
        {
            $this->sent[] = compact('to', 'subject', 'body', 'html');
        }
    };
    app()->instance(RuntimeMailer::class, $mailer);

    return $mailer;
}

it('creates a complex invitation: hash-only token, 7-day expiry, normalised target, email queued', function () {
    ['invitation' => $inv, 'token' => $token] = inviteResident();

    expect($inv->kind)->toBe(InvitationKind::ComplexResident)
        ->and($inv->status)->toBe(InvitationStatus::Pending)
        ->and($inv->invitee_email)->toBe('resident@example.az')
        ->and($inv->invitee_first_name)->toBe('Anar')
        ->and($inv->token_hash)->toBe(hash('sha256', $token))
        ->and($inv->token)->toBeNull()
        ->and($inv->device_id)->toBeNull()
        ->and((int) round(now()->diffInDays($inv->expires_at)))->toBe(7)
        ->and($inv->send_count)->toBe(1)
        ->and(DB::table('invitations')->where('token_hash', $token)->exists())->toBeFalse(); // plaintext never stored

    Bus::assertDispatched(SendInvitationEmailJob::class, fn ($job) => $job->invitationId === $inv->id && $job->token === $token);
    expect(new SendInvitationEmailJob(1, 'x'))->toBeInstanceOf(\Illuminate\Contracts\Queue\ShouldBeEncrypted::class);
});

it('creates a family invitation on the same engine', function () {
    $head = makeUser('+994500030001');
    ['invitation' => $inv] = $this->svc->createFamilyMember($head, 'member@example.az', 'Leyla', 'Əliyeva');

    expect($inv->kind)->toBe(InvitationKind::FamilyMember)
        ->and((int) $inv->invited_by_user_id)->toBe((int) $head->id)
        ->and($inv->complex_id)->toBeNull()
        ->and($inv->invited_by_admin_id)->toBeNull();
});

it('rejects a duplicate live invitation, but allows one after revoke or expiry, and per complex', function () {
    ['invitation' => $first] = inviteResident();
    expect(fn () => inviteResident('resident@example.az'))->toThrow(InvitationException::class);

    $this->svc->revoke($first, byAdmin: $this->komendant);
    expect($first->fresh()->status)->toBe(InvitationStatus::Cancelled)
        ->and($first->fresh()->revoked_at)->not->toBeNull();

    ['invitation' => $second] = inviteResident();
    $this->travel(8)->days(); // stale pending (not swept yet) must not block
    ['invitation' => $third] = inviteResident();
    expect($second->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($third->status)->toBe(InvitationStatus::Pending);

    $other = makeComplex('CX-INV-2', 'Other');
    expect($this->svc->createComplexResident($other->id, $this->komendant, 'resident@example.az', 'A', 'B')['invitation']->status)
        ->toBe(InvitationStatus::Pending);
});

it('rejects an invalid target', function () {
    expect(fn () => inviteResident('not-an-email'))->toThrow(InvitationException::class)
        ->and(fn () => $this->svc->createComplexResident($this->complex->id, $this->komendant, 'a@b.az', '', 'X'))->toThrow(InvitationException::class);
});

it('finds only live invitations — unknown / expired / revoked / accepted all resolve to the same null', function () {
    ['invitation' => $inv, 'token' => $token] = inviteResident();
    expect($this->svc->findLive($token)?->id)->toBe($inv->id)
        ->and($this->svc->findLive('nope'))->toBeNull()
        ->and($this->svc->findLive(''))->toBeNull();

    $inv->forceFill(['status' => InvitationStatus::Accepted->value])->save();
    expect($this->svc->findLive($token))->toBeNull();

    ['invitation' => $b, 'token' => $tb] = inviteResident('b@example.az');
    $this->svc->revoke($b, byAdmin: $this->komendant);
    expect($this->svc->findLive($tb))->toBeNull();

    ['token' => $tc] = inviteResident('c@example.az');
    $this->travel(7)->days();
    $this->travel(1)->minutes();
    expect($this->svc->findLive($tc))->toBeNull();
});

it('resend rotates the token (old link dies), extends expiry and enforces cooldown + daily cap', function () {
    ['invitation' => $inv, 'token' => $old] = inviteResident();

    expect(fn () => $this->svc->resend($inv))->toThrow(InvitationException::class); // < 60 s cooldown

    $this->travel(2)->days();
    $new = $this->svc->resend($inv->fresh());
    expect($new)->not->toBe($old)
        ->and($this->svc->findLive($old))->toBeNull()
        ->and($this->svc->findLive($new)?->id)->toBe($inv->id)
        ->and((int) round(now()->diffInDays($inv->fresh()->expires_at)))->toBe(7)
        ->and($inv->fresh()->send_count)->toBe(2);

    for ($i = 0; $i < 4; $i++) {      // 5 resends per invitation per day
        $this->travel(61)->seconds();
        $this->svc->resend($inv->fresh());
    }
    $this->travel(61)->seconds();
    try {
        $this->svc->resend($inv->fresh());
        $this->fail('6th resend in a day must be rate limited');
    } catch (InvitationException $e) {
        expect($e->status)->toBe(429)->and($e->errorCode)->toBe('invitation_rate_limited');
    }
});

it('resends a swept-expired invitation back to pending; never an accepted or cancelled one', function () {
    ['invitation' => $inv] = inviteResident();
    $this->travel(8)->days();
    app(ExpireInvitationsJob::class)->handle($this->svc);
    expect($inv->fresh()->status)->toBe(InvitationStatus::Expired);

    $this->svc->resend($inv->fresh());
    expect($inv->fresh()->status)->toBe(InvitationStatus::Pending);

    $this->svc->revoke($inv->fresh(), byAdmin: $this->komendant);
    $this->travel(2)->minutes();
    expect(fn () => $this->svc->resend($inv->fresh()))->toThrow(InvitationException::class)
        ->and(fn () => $this->svc->revoke($inv->fresh()))->toThrow(InvitationException::class);
});

it('caps sends per inviter per day', function () {
    config(['domain.invitations.inviter_max_per_day' => 2]);
    inviteResident('x1@example.az');
    inviteResident('x2@example.az');

    expect(fn () => inviteResident('x3@example.az'))->toThrow(InvitationException::class);
});

it('the hourly sweep expires only past-due pending invitations', function () {
    ['invitation' => $due] = inviteResident('due@example.az');
    $this->travel(3)->days();
    ['invitation' => $fresh] = inviteResident('fresh@example.az');
    $this->travel(5)->days(); // $due is 8 days old, $fresh 5

    expect(app(ExpireInvitationsJob::class)->handle($this->svc))->toBe(1)
        ->and($due->fresh()->status)->toBe(InvitationStatus::Expired)
        ->and($fresh->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('emails the invitation through TemplatedMailer — HTML + plain text carry the link, no OTP wording', function () {
    $mailer = fakeRuntimeMailer();
    ['invitation' => $inv, 'token' => $token] = inviteResident();

    (new SendInvitationEmailJob($inv->id, $token))->handle(app(\App\Domain\Mail\TemplatedMailer::class), app(InvitationTokens::class), app(\App\Support\Time\Clock::class));

    $mail = $mailer->sent[0];
    $link = config('domain.invitations.link_base').'/'.$token;
    expect($mail['to'])->toBe('resident@example.az')
        ->and($mail['subject'])->toContain('dəvət')
        ->and($mail['html'])->toContain($link)->toContain('Salam Həyət Test')->toContain('Anar')
        ->and($mail['body'])->toContain($link)
        ->and($mail['body'])->not->toContain('kodu heç kimlə paylaşmayın');
});

it('does not send a superseded token, a revoked invitation, or when mail is not configured', function () {
    $mailer = fakeRuntimeMailer();
    ['invitation' => $inv, 'token' => $old] = inviteResident();
    $this->travel(2)->minutes();
    $this->svc->resend($inv);
    $job = fn (string $t) => (new SendInvitationEmailJob($inv->id, $t))->handle(app(\App\Domain\Mail\TemplatedMailer::class), app(InvitationTokens::class), app(\App\Support\Time\Clock::class));

    $job($old);                                // rotated away
    $this->svc->revoke($inv->fresh(), byAdmin: $this->komendant);
    $job($old);
    expect($mailer->sent)->toBeEmpty();

    $unconfigured = fakeRuntimeMailer(false);
    ['invitation' => $b, 'token' => $tb] = inviteResident('b@example.az');
    (new SendInvitationEmailJob($b->id, $tb))->handle(app(\App\Domain\Mail\TemplatedMailer::class), app(InvitationTokens::class), app(\App\Support\Time\Clock::class));
    expect($unconfigured->sent)->toBeEmpty()->and($b->fresh()->status)->toBe(InvitationStatus::Pending);
});

it('keeps the original (legacy-shape) phone/device invitation row valid on the generalised table', function () {
    $owner = makeUser('+994500030099');
    $device = makeOwnedDevice($owner, 'SER-INV-1', '+994700030099');

    $id = DB::table('invitations')->insertGetId([
        'device_id' => $device->id, 'invited_by_user_id' => $owner->id, 'invitee_phone' => '+994500030098',
        'role' => 'user', 'payer' => 'owner', 'token' => str_repeat('a', 40), 'status' => 'pending',
        'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(Invitation::query()->find($id)->kind)->toBe(InvitationKind::FamilyMember); // default kind
});

it('still sends OTP emails with the original plain-text wording', function () {
    $mailer = fakeRuntimeMailer();
    app(\App\Domain\Mail\TemplatedMailer::class)->send(\App\Domain\Mail\EmailType::RegistrationOtp, 'u@example.az', ['code' => '123456', 'ttlMinutes' => 5, 'intro' => 'Intro']);

    expect($mailer->sent[0]['body'])->toBe("Intro\nKod: 123456\nKod 5 dəqiqə ərzində etibarlıdır.\nBu kodu heç kimlə paylaşmayın.");
});
