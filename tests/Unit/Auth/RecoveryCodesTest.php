<?php

use App\Domain\Auth\Support\RecoveryCodes;

/*
| Single-use admin recovery codes (R-SEC-06 / CRIT-09).
*/

it('generates 8 ten-hex-char codes with matching bcrypt hashes', function () {
    $set = RecoveryCodes::generate(8, 5);

    expect($set['plaintext'])->toHaveCount(8)
        ->and($set['hashes'])->toHaveCount(8);

    foreach ($set['plaintext'] as $i => $code) {
        expect($code)->toMatch('/^[0-9a-f]{10}$/')
            ->and(\Illuminate\Support\Facades\Hash::check($code, $set['hashes'][$i]))->toBeTrue();
    }
});

it('matches a code case-insensitively and reports the slot index', function () {
    $set = RecoveryCodes::generate(8, 5);
    $target = $set['plaintext'][3];

    expect(RecoveryCodes::match($set['hashes'], strtoupper($target)))->toBe(3)
        ->and(RecoveryCodes::match($set['hashes'], 'ffffffffff'))->toBeNull();
});

it('skips consumed (null) slots and counts the remaining', function () {
    $set = RecoveryCodes::generate(8, 5);
    $hashes = $set['hashes'];
    $hashes[3] = null; // consumed

    expect(RecoveryCodes::match($hashes, $set['plaintext'][3]))->toBeNull()
        ->and(RecoveryCodes::remaining($hashes))->toBe(7);
});
