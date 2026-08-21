<?php

use App\Domain\Auth\Adapters\FakeOtpTransport;
use App\Domain\Auth\Models\Otp;
use App\Domain\Auth\Models\RefreshToken;
use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;

/*
| POST /v1/auth/otp/verify — verifyOtp (AuthSuccess; auto-provision; device + refresh issued).
*/

function requestOtpCode(string $phone): string
{
    test()->postJson('/v1/auth/otp/request', ['phone' => $phone])->assertStatus(202);

    return app(FakeOtpTransport::class)->lastCodeFor($phone);
}

function verifyOtpPayload(string $phone, string $code): array
{
    return [
        'phone' => $phone,
        'code' => $code,
        'device' => [
            'install_uuid' => TEST_INSTALL_UUID,
            'platform' => 'ios',
            'app_version' => '1.0.0',
            'push_token' => 'fcm-token-xyz',
        ],
    ];
}

it('verifies the code, provisions the user, and returns an AuthSuccess', function () {
    $phone = '+994501230001';
    $code = requestOtpCode($phone);

    $response = $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $code));

    $response->assertOk()
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 900)
        ->assertJsonPath('user.phone', $phone)
        ->assertJsonPath('user.has_active_subscription', false)
        ->assertJsonStructure(['access_token', 'refresh_token', 'refresh_expires_in', 'user' => ['id', 'phone', 'status']]);

    $user = User::query()->where('phone', $phone)->firstOrFail();
    expect(UserDevice::query()->where('user_id', $user->id)->where('install_uuid', TEST_INSTALL_UUID)->exists())->toBeTrue()
        ->and(RefreshToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(1)
        ->and($user->last_login_at)->not->toBeNull();
});

it('consumes the OTP so it cannot be reused', function () {
    $phone = '+994501230002';
    $code = requestOtpCode($phone);

    $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $code))->assertOk();
    $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $code))
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'wrong_code');
});

it('rejects a wrong code', function () {
    $phone = '+994501230003';
    requestOtpCode($phone);

    $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, '000000'))
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'wrong_code');
});

it('locks the code after the maximum number of attempts', function () {
    $phone = '+994501230004';
    $realCode = requestOtpCode($phone);
    $wrong = $realCode === '000000' ? '111111' : '000000';

    for ($i = 1; $i <= 4; $i++) {
        $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $wrong))
            ->assertJsonPath('error.code', 'wrong_code');
    }

    $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $wrong))
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'otp_max_attempts');
});

it('reports an expired code', function () {
    $phone = '+994501230005';
    $code = requestOtpCode($phone);

    Otp::query()->where('phone', $phone)->update(['expires_at' => now()->subSecond()]);

    $this->postJson('/v1/auth/otp/verify', verifyOtpPayload($phone, $code))
        ->assertStatus(401)
        ->assertJsonPath('error.code', 'otp_expired');
});

it('validates the device fingerprint', function () {
    $phone = '+994501230006';
    $code = requestOtpCode($phone);

    $this->postJson('/v1/auth/otp/verify', [
        'phone' => $phone,
        'code' => $code,
        'device' => ['platform' => 'ios'], // missing install_uuid
    ])->assertStatus(422);
});
