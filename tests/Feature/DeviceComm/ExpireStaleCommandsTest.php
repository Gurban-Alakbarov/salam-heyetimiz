<?php

use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Jobs\ExpireStaleOpenCommandsJob;
use App\Domain\Roster\Models\DeviceUser;

/*
| Lifecycle safety net: stale queued/dispatching commands are moved to terminal `expired`.
*/

it('expires stale queued commands and leaves fresh ones alone', function () {
    $owner = makeUser('+994509600001');
    $device = makeOpenableDevice($owner, 'SER-EX1', '+994700096001');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();

    $stale = makeOpenCommand($device, $owner, $du, ['state' => 'queued', 'requested_at' => now()->subMinutes(5)]);
    $fresh = makeOpenCommand($device, $owner, $du, ['state' => 'queued', 'requested_at' => now()]);

    $expired = app()->call([new ExpireStaleOpenCommandsJob(), 'handle']);

    expect($expired)->toBe(1)
        ->and($stale->refresh()->state)->toBe(OpenCommandState::Expired)
        ->and($fresh->refresh()->state)->toBe(OpenCommandState::Queued);
});

it('does not re-expire a terminal command', function () {
    $owner = makeUser('+994509600002');
    $device = makeOpenableDevice($owner, 'SER-EX2', '+994700096002');
    $du = DeviceUser::query()->where('device_id', $device->id)->where('user_id', $owner->id)->firstOrFail();

    $done = makeOpenCommand($device, $owner, $du, ['state' => 'dispatched', 'requested_at' => now()->subMinutes(5), 'completed_at' => now()->subMinutes(5)]);

    app()->call([new ExpireStaleOpenCommandsJob(), 'handle']);

    expect($done->refresh()->state)->toBe(OpenCommandState::Dispatched);
});
