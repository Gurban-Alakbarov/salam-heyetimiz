<?php

use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Users\Models\User;

/*
| K6 — the legacy phone-OTP sign-in is retired. POST /v1/auth/otp/request|verify stay routed (throttled) but
| answer 410 sms_otp_retired in the standard error envelope: no SMS is sent, no account is provisioned from
| a bare phone number, no tokens are issued — for unknown and existing phones alike.
*/

it('request answers 410 sms_otp_retired, sends nothing and provisions nobody', function () {
    $this->postJson('/v1/auth/otp/request', ['phone' => '+994501110011'])
        ->assertStatus(410)
        ->assertJsonPath('error.code', 'sms_otp_retired')
        ->assertJsonPath('error.message_key', 'errors.sms_otp_retired');

    expect(app(FakeOtpTransport::class)->lastCodeFor('+994501110011'))->toBeNull()
        ->and(User::query()->where('phone', '+994501110011')->exists())->toBeFalse();
});

it('verify answers 410 and never issues tokens, even for an existing account', function () {
    $user = makeUser('+994501110012');

    $this->postJson('/v1/auth/otp/verify', [
        'phone' => '+994501110012',
        'code' => '123456',
        'device' => ['install_uuid' => TEST_INSTALL_UUID, 'platform' => 'ios'],
    ])->assertStatus(410)->assertJsonPath('error.code', 'sms_otp_retired');

    expect(RefreshToken::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and(User::query()->where('phone', '+994501110012')->count())->toBe(1);
});

it('answers 410 whatever the body (no validation path left to probe)', function () {
    $this->postJson('/v1/auth/otp/request', [])->assertStatus(410);
    $this->postJson('/v1/auth/otp/verify', ['phone' => 'not-a-phone'])->assertStatus(410);
});
