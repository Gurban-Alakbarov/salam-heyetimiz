<?php

/*
| GET /.well-known/jwks.json — getJwks (admin verification keys; CRIT-04 / R-SEC-08).
*/

it('publishes the admin signing key as a JWKS document', function () {
    $response = $this->getJson('/.well-known/jwks.json');

    $response->assertOk()
        ->assertJsonStructure(['keys' => [['kty', 'kid', 'use', 'alg', 'n', 'e']]])
        ->assertJsonPath('keys.0.kty', 'RSA')
        ->assertJsonPath('keys.0.alg', 'RS256')
        ->assertJsonPath('keys.0.use', 'sig')
        ->assertJsonPath('keys.0.kid', config('domain.auth.access.admin.kid'));
});
