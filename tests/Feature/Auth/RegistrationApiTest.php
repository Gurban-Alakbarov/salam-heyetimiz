<?php

use App\Domain\Auth\Adapters\FakeEmailOtpTransport;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
| Phase 2 — Email-OTP registration + login API. Unified envelope { success, message, data, meta, errors }
| on every NEW endpoint; the legacy phone-OTP endpoints stay in their own shape (asserted untouched).
| No real SMTP (the email transport is the in-memory fake).
*/

function emailOtp(string $email): string
{
    return (string) app(FakeEmailOtpTransport::class)->lastCodeFor(mb_strtolower($email));
}

function devicePayload(?string $uuid = null): array
{
    return ['install_uuid' => $uuid ?? (string) Str::uuid(), 'platform' => 'ios', 'app_version' => '1.0.0'];
}

function registerBody(string $email = 'aysel@test.az', string $phone = '+994501234567'): array
{
    return ['first_name' => 'Aysel', 'last_name' => 'Məmmədova', 'phone' => $phone, 'email' => $email];
}

it('registers a new account (unified envelope) and stores it unverified', function () {
    $res = test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);

    $res->assertJsonStructure(['success', 'message', 'data', 'meta' => ['expires_in_seconds', 'resend_available_in_seconds'], 'errors'])
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null)
        ->assertJsonPath('errors', null);

    $user = User::query()->where('email', 'aysel@test.az')->first();
    expect($user)->not->toBeNull()
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->full_name)->toBe('Aysel Məmmədova')
        ->and(emailOtp('aysel@test.az'))->toMatch('/^\d{6}$/');

    // OTP-requested audit recorded.
    expect(DB::table('audit_logs')->where('action', 'auth.email_otp_requested')->exists())->toBeTrue();
});

it('verifies the email OTP, auto-logs-in, and writes everything in one transaction', function () {
    test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);
    $uuid = (string) Str::uuid();

    $res = test()->postJson('/v1/auth/verify-email', [
        'email' => 'aysel@test.az', 'code' => emailOtp('aysel@test.az'), 'device' => devicePayload($uuid),
    ])->assertOk();

    $res->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Qeydiyyat tamamlandı.')
        ->assertJsonStructure([
            'success', 'message',
            'data' => ['access_token', 'refresh_token', 'token_type', 'expires_in', 'refresh_expires_in',
                'user' => ['id', 'phone', 'email', 'email_verified_at', 'status']],
            'meta', 'errors',
        ])
        ->assertJsonPath('data.user.email_verified_at', fn ($v) => $v !== null);

    $user = User::query()->where('email', 'aysel@test.az')->first();
    expect($user->email_verified_at)->not->toBeNull()
        ->and($user->last_login_at)->not->toBeNull()
        ->and($user->last_login_ip)->not->toBeNull();

    // §6 — device + refresh token written atomically with the verification.
    expect(DB::table('user_devices')->where('user_id', $user->id)->where('install_uuid', $uuid)->exists())->toBeTrue()
        ->and(DB::table('refresh_tokens')->where('user_id', $user->id)->exists())->toBeTrue();

    // audit events
    expect(DB::table('audit_logs')->where('action', 'auth.user_registered')->exists())->toBeTrue()
        ->and(DB::table('audit_logs')->where('action', 'auth.user_authenticated')->exists())->toBeTrue();
});

it('rejects a wrong OTP with the unified error envelope', function () {
    test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);

    test()->postJson('/v1/auth/verify-email', ['email' => 'aysel@test.az', 'code' => '000000', 'device' => devicePayload()])
        ->assertStatus(401)
        ->assertJsonPath('success', false)
        ->assertJsonPath('data', null)
        ->assertJsonPath('errors.code', 'wrong_code');
});

it('reuses the unverified account on a duplicate register (no duplicate, new OTP) — brief §4', function () {
    test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);
    $first = emailOtp('aysel@test.az');

    test()->postJson('/v1/auth/register', registerBody(phone: '+994500000099'))->assertStatus(202);
    $second = emailOtp('aysel@test.az');

    expect(User::query()->where('email', 'aysel@test.az')->count())->toBe(1)
        ->and(User::query()->where('email', 'aysel@test.az')->first()->phone)->toBe('+994500000099')
        ->and($second)->not->toBe($first); // a fresh code superseded the old one
});

it('refuses to register an already-verified email professionally — brief §5', function () {
    test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);
    test()->postJson('/v1/auth/verify-email', ['email' => 'aysel@test.az', 'code' => emailOtp('aysel@test.az'), 'device' => devicePayload()])->assertOk();

    test()->postJson('/v1/auth/register', registerBody())
        ->assertStatus(409)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.code', 'email_already_registered')
        ->assertJsonPath('message', 'Bu email artıq qeydiyyatdan keçib. Zəhmət olmasa giriş edin.');

    expect(User::query()->where('email', 'aysel@test.az')->count())->toBe(1);
});

it('logs a verified user in via email OTP through the SAME verify endpoint — brief §3', function () {
    // register + verify (become verified)
    test()->postJson('/v1/auth/register', registerBody())->assertStatus(202);
    test()->postJson('/v1/auth/verify-email', ['email' => 'aysel@test.az', 'code' => emailOtp('aysel@test.az'), 'device' => devicePayload()])->assertOk();

    // login → sends a login OTP
    test()->postJson('/v1/auth/login', ['email' => 'aysel@test.az'])->assertStatus(202)->assertJsonPath('success', true);

    // verify the login OTP via the shared endpoint → wasRegistration=false
    test()->postJson('/v1/auth/verify-email', ['email' => 'aysel@test.az', 'code' => emailOtp('aysel@test.az'), 'device' => devicePayload()])
        ->assertOk()
        ->assertJsonPath('message', 'Giriş uğurla tamamlandı.')
        ->assertJsonPath('data.token_type', 'Bearer');
});

it('does not leak account existence on login/resend (anti-enumeration)', function () {
    test()->postJson('/v1/auth/login', ['email' => 'ghost@test.az'])
        ->assertStatus(202)->assertJsonPath('success', true)
        ->assertJsonStructure(['meta' => ['expires_in_seconds', 'resend_available_in_seconds']]);
    expect(emailOtp('ghost@test.az'))->toBe(''); // nothing actually sent

    test()->postJson('/v1/auth/resend-otp', ['email' => 'ghost@test.az'])->assertStatus(202)->assertJsonPath('success', true);
});

it('returns unified 422 for validation errors', function () {
    test()->postJson('/v1/auth/register', ['first_name' => '', 'last_name' => 'X', 'phone' => 'bad', 'email' => 'notanemail'])
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonStructure(['success', 'message', 'data', 'meta', 'errors' => ['first_name', 'phone', 'email']]);
});

it('rejects a phone already taken by a verified account', function () {
    // user A verified with a phone
    test()->postJson('/v1/auth/register', registerBody('a@test.az', '+994557776655'))->assertStatus(202);
    test()->postJson('/v1/auth/verify-email', ['email' => 'a@test.az', 'code' => emailOtp('a@test.az'), 'device' => devicePayload()])->assertOk();

    // user B tries the same phone with a different email
    test()->postJson('/v1/auth/register', registerBody('b@test.az', '+994557776655'))
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.phone.0', 'Bu telefon nömrəsi artıq istifadədədir.');
});

it('rate-limits register per email with a unified 429', function () {
    for ($i = 0; $i < 5; $i++) {
        test()->postJson('/v1/auth/register', registerBody('rl@test.az', '+99450000'.str_pad((string) $i, 4, '0', STR_PAD_LEFT)))->assertStatus(202);
    }
    test()->postJson('/v1/auth/register', registerBody('rl@test.az'))
        ->assertStatus(429)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errors.code', 'rate_limited');
});

it('leaves the legacy phone-OTP endpoints in their original shape (untouched)', function () {
    // legacy request returns the bare {expires_in_seconds,...} (NOT the unified envelope)
    test()->postJson('/v1/auth/otp/request', ['phone' => '+994509990001'])
        ->assertStatus(202)
        ->assertJsonStructure(['expires_in_seconds', 'resend_available_in_seconds'])
        ->assertJsonMissingPath('success');
});
