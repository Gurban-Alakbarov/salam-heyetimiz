<?php

use App\Domain\Auth\Support\Totp;

/*
| RFC 6238 TOTP (R-SEC-05). Pure; deterministic against a fixed timestamp.
*/

beforeEach(function (): void {
    $this->totp = new Totp(period: 30, digits: 6, algorithm: 'sha1', window: 1);
    $this->secret = 'JBSWY3DPEHPK3PXP';
});

it('verifies a code generated for the same instant', function () {
    $ts = 1_700_000_000;
    $code = $this->totp->at($this->secret, $ts);

    expect($code)->toMatch('/^\d{6}$/')
        ->and($this->totp->verify($this->secret, $code, $ts))->toBeTrue();
});

it('accepts a code from the adjacent step within the drift window', function () {
    $ts = 1_700_000_000;
    $previousStepCode = $this->totp->at($this->secret, $ts - 30);

    expect($this->totp->verify($this->secret, $previousStepCode, $ts))->toBeTrue();
});

it('rejects a wrong code', function () {
    $ts = 1_700_000_000;
    $code = $this->totp->at($this->secret, $ts);
    $wrong = str_pad((string) ((((int) $code) + 1) % 1_000_000), 6, '0', STR_PAD_LEFT);

    expect($this->totp->verify($this->secret, $wrong, $ts))->toBeFalse();
});

it('rejects a code outside the drift window', function () {
    $ts = 1_700_000_000;
    $farCode = $this->totp->at($this->secret, $ts - 300); // 10 steps away

    expect($this->totp->verify($this->secret, $farCode, $ts))->toBeFalse();
});

it('matches a known RFC 6238 SHA-1 test vector', function () {
    // RFC 6238 Appendix B: ASCII secret "12345678901234567890" at T=59 → 94287082 → 6-digit 287082.
    $secret = (new Totp())->verify('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', '287082', 59);

    expect($secret)->toBeTrue();
});

it('generates a usable base32 secret', function () {
    $secret = $this->totp->generateSecret();
    $code = $this->totp->at($secret, 1_700_000_000);

    expect($this->totp->verify($secret, $code, 1_700_000_000))->toBeTrue();
});
