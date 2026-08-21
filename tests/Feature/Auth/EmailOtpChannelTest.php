<?php

use App\Domain\Admin\Services\SettingsService;
use App\Domain\Admin\Settings\RuntimeMailer;
use App\Domain\Admin\Settings\SettingsConfigBridge;
use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Enums\OtpPurpose;
use App\Domain\Auth\Exceptions\OtpVerificationException;
use App\Domain\Auth\Models\Otp;
use App\Domain\Auth\Services\OtpService;
use App\Domain\Mail\EmailType;
use App\Domain\Mail\TemplatedMailer;

/*
| Phase 1 — Email OTP channel + generic email template engine. The phone/SMS path must remain
| byte-for-byte; the email path reuses the same engine + Settings tunables. No real SMTP in tests
| (the email transport is the in-memory fake, like FakeOtpTransport).
*/

/** Test double: captures what RuntimeMailer would have sent, instead of opening SMTP. */
class CapturingRuntimeMailer extends RuntimeMailer
{
    /** @var array<int, array{to:string,subject:string,body:string,html:?string}> */
    public array $sent = [];

    public function send(string $to, string $subject, string $body, ?string $html = null): void
    {
        $this->sent[] = compact('to', 'subject', 'body', 'html');
    }
}

function emailCode(string $email): string
{
    return (string) app(FakeEmailOtpTransport::class)->lastCodeFor($email);
}

it('renders the HTML OTP template with code, TTL and security warning', function () {
    $html = view('emails.otp', [
        'code' => '987654', 'ttlMinutes' => 3, 'intro' => 'Giriş kodunuz:',
        'brand' => 'Salam Həyətimiz', 'supportEmail' => 'support@salamheyetimiz.com',
    ])->render();

    expect($html)->toContain('987654')->toContain('3 dəqiqə')
        ->toContain('paylaşmayın')->toContain('Salam Həyətimiz')->toContain('support@salamheyetimiz.com');
});

it('composes an email via TemplatedMailer: subject from EmailType + HTML + text fallback', function () {
    $cap = new CapturingRuntimeMailer(app(SettingsService::class));
    app()->instance(RuntimeMailer::class, $cap);

    app(TemplatedMailer::class)->send(EmailType::RegistrationOtp, 'compose@test.az', [
        'code' => '123456', 'ttlMinutes' => 2, 'intro' => 'Qeydiyyat kodunuz:', 'brand' => 'Salam Həyətimiz',
    ]);

    expect($cap->sent)->toHaveCount(1);
    $msg = $cap->sent[0];
    expect($msg['to'])->toBe('compose@test.az')
        ->and($msg['subject'])->toBe('Salam Həyətimiz — qeydiyyat təsdiq kodu')
        ->and($msg['html'])->toContain('123456')->toContain('2 dəqiqə')
        ->and($msg['body'])->toContain('123456'); // plain-text fallback present
});

it('sends html=null when email.html_enabled is off (toggle honoured)', function () {
    app(SettingsService::class)->set('email.html_enabled', '0');
    $cap = new CapturingRuntimeMailer(app(SettingsService::class));
    app()->instance(RuntimeMailer::class, $cap);

    app(TemplatedMailer::class)->send(EmailType::LoginOtp, 'plain@test.az', ['code' => '111111', 'ttlMinutes' => 2]);

    expect($cap->sent[0]['html'])->toBeNull()
        ->and($cap->sent[0]['body'])->toContain('111111');
});

it('issues + verifies an email OTP through the shared engine', function () {
    $r = app(OtpService::class)->issueToEmail('reg@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');

    expect($r['expires_in_seconds'])->toBeGreaterThan(0)
        ->and($r['resend_available_in_seconds'])->toBeGreaterThan(0);

    $code = emailCode('reg@test.az');
    expect($code)->toMatch('/^\d{6}$/');

    $otp = Otp::query()->where('email', 'reg@test.az')->first();
    expect($otp->channel)->toBe('email')->and($otp->phone)->toBeNull()->and($otp->consumed_at)->toBeNull();

    app(OtpService::class)->verifyByEmail('reg@test.az', $code, OtpPurpose::EmailVerify); // must not throw
    expect(Otp::query()->where('email', 'reg@test.az')->first()->consumed_at)->not->toBeNull();
});

it('rejects wrong, expired, used and over-attempt email codes', function () {
    $svc = app(OtpService::class);

    // wrong code
    $svc->issueToEmail('w@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');
    expect(fn () => $svc->verifyByEmail('w@test.az', '000000', OtpPurpose::EmailVerify))
        ->toThrow(OtpVerificationException::class);

    // expired
    $svc->issueToEmail('e@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');
    Otp::query()->where('email', 'e@test.az')->update(['expires_at' => now()->subMinute()]);
    expect(fn () => $svc->verifyByEmail('e@test.az', emailCode('e@test.az'), OtpPurpose::EmailVerify))
        ->toThrow(OtpVerificationException::class);

    // over max attempts (set counter to the cap → next verify is rejected before the hash check)
    $svc->issueToEmail('m@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');
    Otp::query()->where('email', 'm@test.az')->update(['attempts' => 5]);
    expect(fn () => $svc->verifyByEmail('m@test.az', emailCode('m@test.az'), OtpPurpose::EmailVerify))
        ->toThrow(OtpVerificationException::class);

    // single-use: a consumed code can't be reused
    $svc->issueToEmail('u@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');
    $code = emailCode('u@test.az');
    $svc->verifyByEmail('u@test.az', $code, OtpPurpose::EmailVerify);
    expect(fn () => $svc->verifyByEmail('u@test.az', $code, OtpPurpose::EmailVerify))
        ->toThrow(OtpVerificationException::class);
});

it('reads OTP TTL + resend from Settings (no hardcode)', function () {
    $svc = app(SettingsService::class);
    $svc->set('otp.otp_ttl_seconds', '300');
    $svc->set('otp.otp_resend_seconds', '45');
    app(SettingsConfigBridge::class)->apply($svc);

    $r = app(OtpService::class)->issueToEmail('ttl@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');

    expect($r['expires_in_seconds'])->toBe(300)
        ->and($r['resend_available_in_seconds'])->toBe(45);

    $otp = Otp::query()->where('email', 'ttl@test.az')->first();
    expect(abs($otp->expires_at->diffInSeconds($otp->created_at)))->toBeGreaterThanOrEqual(295);
});

it('does not break the phone/SMS path and keeps the two channels independent', function () {
    $svc = app(OtpService::class);

    // existing phone path unchanged
    $svc->issue('+994501112233', OtpPurpose::Login, '127.0.0.1', 'az');
    $pcode = (string) app(FakeOtpTransport::class)->lastCodeFor('+994501112233');
    $svc->verifyByEmail('+994501112233', $pcode, OtpPurpose::Login); // wrong channel must NOT match
    // (the line above should throw because the phone code lives on the SMS channel, not email)
})->throws(OtpVerificationException::class);

it('keeps phone and email OTP rows isolated by channel', function () {
    $svc = app(OtpService::class);
    $svc->issue('+994509998877', OtpPurpose::Login, '127.0.0.1', 'az');
    $svc->issueToEmail('iso@test.az', OtpPurpose::EmailVerify, '127.0.0.1', 'az');

    $phoneRow = Otp::query()->where('phone', '+994509998877')->first();
    $emailRow = Otp::query()->where('email', 'iso@test.az')->first();

    expect($phoneRow->channel)->toBe('sms')->and($phoneRow->email)->toBeNull()
        ->and($emailRow->channel)->toBe('email')->and($emailRow->phone)->toBeNull();

    // phone code still verifies on the phone path
    $svc->verify('+994509998877', (string) app(FakeOtpTransport::class)->lastCodeFor('+994509998877'));
    expect(Otp::query()->where('phone', '+994509998877')->first()->consumed_at)->not->toBeNull();
    // email row untouched by the phone verify
    expect(Otp::query()->where('email', 'iso@test.az')->first()->consumed_at)->toBeNull();
});
