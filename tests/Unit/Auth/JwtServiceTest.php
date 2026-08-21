<?php

use App\Domain\Auth\Services\JwtService;

/*
| RS256 JWT issuance/verification (R-SEC-02/05/08). Uses the on-disk dev keypairs.
*/

beforeEach(function (): void {
    $this->jwt = app(JwtService::class);
});

it('issues and verifies a mobile access token carrying sub/kind/fp', function () {
    $issued = $this->jwt->issueUserAccessToken(42, 'install-abc');

    expect($issued->token)->toBeString()
        ->and($issued->expiresIn)->toBe(900);

    $claims = $this->jwt->parseUserClaims($issued->token);

    expect($claims)->not->toBeNull()
        ->and($claims['user_id'])->toBe(42)
        ->and($claims['fp'])->toBe('install-abc');
});

it('rejects a tampered or garbage token', function () {
    $issued = $this->jwt->issueUserAccessToken(42, 'install-abc');

    expect($this->jwt->parseUserClaims($issued->token.'x'))->toBeNull()
        ->and($this->jwt->parseUserClaims('not-a-jwt'))->toBeNull();
});

it('does not accept a user token on the admin guard (disjoint keys/audience)', function () {
    $userToken = $this->jwt->issueUserAccessToken(42, 'install-abc')->token;

    expect($this->jwt->parseAdminClaims($userToken))->toBeNull();
});

it('issues and verifies an admin access token with tfa_verified', function () {
    $issued = $this->jwt->issueAdminAccessToken(7, 'super_admin');

    expect($issued->expiresIn)->toBe(1800);

    $claims = $this->jwt->parseAdminClaims($issued->token);

    expect($claims)->not->toBeNull()
        ->and($claims['admin_id'])->toBe(7)
        ->and($claims['role'])->toBe('super_admin')
        ->and($claims['tfa_verified'])->toBeTrue();
});

it('treats a challenge token as a challenge, not an access token', function () {
    $challenge = $this->jwt->issueAdminChallengeToken(7);

    expect($this->jwt->readChallengeAdminId($challenge))->toBe(7)
        ->and($this->jwt->parseAdminClaims($challenge))->toBeNull(); // kind=admin_challenge ≠ admin
});
