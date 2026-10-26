<?php

use App\Domain\Auth\Models\WidgetToken;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Bus;

/*
| Home-screen widget token: the widget opens a door with its own open-only, install-bound, long-lived token,
| so a driver at the gate is never told "session expired" because the app's 15-minute access token lapsed.
| It works ONLY for open + command status; logout, re-issue, account state and expiry end it.
*/

beforeEach(fn () => Bus::fake());

function wgtCall($test, string $method, string $uri, string $token, array $body = [], array $headers = [])
{
    app('auth')->forgetGuards(); // each request authenticates from its own bearer

    return $test->json($method, $uri, $body, ['Authorization' => 'Bearer '.$token] + $headers);
}

function wgtSetup($test, string $phone, string $serial, string $sim): array
{
    $tokens = authenticateViaOtp($phone);
    $user = User::query()->where('phone', $phone)->firstOrFail();
    $device = makeOpenableDevice($user, $serial, $sim);
    $widget = wgtCall($test, 'POST', '/v1/me/widget-token', $tokens['access_token'])->assertCreated()->json('data');

    return [$user, $device, $tokens, $widget['token']];
}

it('issues an open-only token that opens the door and polls the command', function () {
    [$user, $device, , $widget] = wgtSetup($this, '+994501200001', 'SER-WGT-1', '+994700120001');
    expect($widget)->toStartWith('wgt_')
        ->and(WidgetToken::query()->where('user_id', $user->id)->whereNull('revoked_at')->count())->toBe(1);

    $commandId = wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $widget, [], ['Idempotency-Key' => 'wgt-open-1'])
        ->assertStatus(202)->json('command_id');
    wgtCall($this, 'GET', "/v1/commands/{$commandId}", $widget)->assertOk();
});

it('keeps working long after the app access token expired', function () {
    [, $device, $tokens, $widget] = wgtSetup($this, '+994501200002', 'SER-WGT-2', '+994700120002');

    $this->travel(2)->days();

    wgtCall($this, 'GET', '/v1/me', $tokens['access_token'])->assertStatus(401); // the app's token is long gone
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $widget, [], ['Idempotency-Key' => 'wgt-open-2'])->assertStatus(202);
});

it('is refused everywhere except open and command status', function () {
    [, $device, , $widget] = wgtSetup($this, '+994501200003', 'SER-WGT-3', '+994700120003');

    wgtCall($this, 'GET', '/v1/me', $widget)->assertStatus(401);
    wgtCall($this, 'GET', '/v1/devices', $widget)->assertStatus(401);
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/visitor-links", $widget, ['access_type' => 'one_time'])->assertStatus(401);
    wgtCall($this, 'POST', '/v1/me/widget-token', $widget)->assertStatus(401);
});

it('ends on re-issue, logout, a blocked account and after its lifetime', function () {
    [$user, $device, $tokens, $first] = wgtSetup($this, '+994501200004', 'SER-WGT-4', '+994700120004');

    $second = wgtCall($this, 'POST', '/v1/me/widget-token', $tokens['access_token'])->assertCreated()->json('data.token');
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $first, [], ['Idempotency-Key' => 'wgt-a'])->assertStatus(401);
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $second, [], ['Idempotency-Key' => 'wgt-b'])->assertStatus(202);

    $user->forceFill(['status' => 'blocked'])->save();
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $second, [], ['Idempotency-Key' => 'wgt-c'])->assertStatus(401);
    $user->forceFill(['status' => 'active'])->save();

    $this->travel(91)->days();
    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $second, [], ['Idempotency-Key' => 'wgt-d'])->assertStatus(401);
});

it('is revoked when the install logs out', function () {
    [, $device, $tokens, $widget] = wgtSetup($this, '+994501200005', 'SER-WGT-5', '+994700120005');

    wgtCall($this, 'POST', '/v1/auth/logout', $tokens['access_token'])->assertNoContent();

    wgtCall($this, 'POST', "/v1/devices/{$device->id}/open", $widget, [], ['Idempotency-Key' => 'wgt-e'])->assertStatus(401);
});
