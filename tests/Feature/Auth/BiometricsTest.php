<?php

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Users\Models\User;

/*
| POST /v1/me/biometrics/enroll + DELETE /v1/me/biometrics (per-install trust flag, R-SEC-04).
*/

it('enrolls and disables biometric unlock for the calling install', function () {
    $phone = '+994504440001';
    $tokens = authenticateViaOtp($phone);
    $headers = bearer($tokens['access_token']);

    $user = User::query()->where('phone', $phone)->firstOrFail();
    $deviceId = UserDevice::query()->where('user_id', $user->id)->value('id');

    $this->withHeaders($headers)->postJson('/v1/me/biometrics/enroll')->assertStatus(204);
    expect(UserDevice::query()->whereKey($deviceId)->value('biometric_enrolled'))->toBeTrue();

    $this->withHeaders($headers)->deleteJson('/v1/me/biometrics')->assertStatus(204);
    expect(UserDevice::query()->whereKey($deviceId)->value('biometric_enrolled'))->toBeFalse();
});

it('requires authentication', function () {
    $this->postJson('/v1/me/biometrics/enroll')->assertStatus(401);
});
