<?php

use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Models\Otp;

/*
| POST /v1/auth/otp/request — requestOtp (202, anti-enumeration, hashed code).
*/

it('issues and dispatches an OTP and returns the timing envelope', function () {
    $phone = '+994501110011';

    $response = $this->postJson('/v1/auth/otp/request', ['phone' => $phone]);

    $response->assertStatus(202)
        ->assertJsonPath('expires_in_seconds', 120)
        ->assertJsonPath('resend_available_in_seconds', 30);

    // The code reached the (fake) transport and is stored only as a hash.
    $code = app(FakeOtpTransport::class)->lastCodeFor($phone);
    expect($code)->toMatch('/^\d{6}$/');

    $otp = Otp::query()->where('phone', $phone)->firstOrFail();
    expect($otp->code_hash)->toBe(hash('sha256', $code))
        ->and($otp->code_hash)->not->toBe($code);
});

it('returns the same shape whether or not an account exists (anti-enumeration)', function () {
    $existing = makeUser('+994501110012');

    $known = $this->postJson('/v1/auth/otp/request', ['phone' => '+994501110012']);
    $unknown = $this->postJson('/v1/auth/otp/request', ['phone' => '+994559990012']);

    expect($known->json())->toEqual($unknown->json());
});

it('supersedes a prior unconsumed code for the same phone+purpose', function () {
    $phone = '+994501110013';

    $this->postJson('/v1/auth/otp/request', ['phone' => $phone]);
    $this->postJson('/v1/auth/otp/request', ['phone' => $phone]);

    expect(Otp::query()->where('phone', $phone)->whereNull('consumed_at')->count())->toBe(1);
});

it('validates the phone format', function () {
    $this->postJson('/v1/auth/otp/request', ['phone' => '0501234567'])->assertStatus(422);
    $this->postJson('/v1/auth/otp/request', ['phone' => '+15551234567'])->assertStatus(422);
});
