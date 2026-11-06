<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Behind the front nginx the visitor's address comes from X-Forwarded-For, and only that proxy is believed.
*/

beforeEach(function () {
    Route::get('/test-client-ip', fn (Request $request) => response()->json(['ip' => $request->ip()]));
});

function clientIp(string $remoteAddr, ?string $forwardedFor = null): string
{
    $headers = $forwardedFor !== null ? ['X-Forwarded-For' => $forwardedFor] : [];

    return test()->withServerVariables(['REMOTE_ADDR' => $remoteAddr])->getJson('/test-client-ip', $headers)->json('ip');
}

it('takes the visitor from the front proxy', function () {
    expect(clientIp('10.10.210.10', '37.61.112.155'))->toBe('37.61.112.155');
});

it('ignores what a client wrote into the header itself', function () {
    expect(clientIp('10.10.210.10', '1.2.3.4, 37.61.121.27'))->toBe('37.61.121.27');
});

it('believes the header only from the front proxy', function () {
    expect(clientIp('203.0.113.9', '1.2.3.4'))->toBe('203.0.113.9');
});
