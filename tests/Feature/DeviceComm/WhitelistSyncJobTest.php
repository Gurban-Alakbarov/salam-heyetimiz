<?php

use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Enums\WhitelistChangeStatus;
use App\Domain\DeviceComm\Jobs\WhitelistSyncJob;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Domain\DeviceComm\Services\WhitelistService;
use App\Support\Time\Clock;

/*
| WhitelistSyncJob (R-GSM-07): drains due `pending` whitelist_changes via the resolved driver. Under the
| v1.2 Traccar/server-authorised model add/remove are no-ops that complete immediately as `synced`.
*/

function runWhitelistSync(): int
{
    return app(WhitelistSyncJob::class)->handle(app(DriverResolver::class), app(Clock::class));
}

it('drains a pending add change to synced via the resolved driver', function () {
    [$device] = openableWithRoster('+994553000001', 'WL-1', '+994700130001');
    app(WhitelistService::class)->enqueue($device, WhitelistAction::Add, '+994553000001');

    $processed = runWhitelistSync();

    expect($processed)->toBe(1);
    $change = WhitelistChange::query()->where('device_id', $device->id)->firstOrFail();
    expect($change->status)->toBe(WhitelistChangeStatus::Synced)
        ->and($change->synced_at)->not->toBeNull();
});

it('drains changes in (priority, seq) order and clears the pending queue', function () {
    [$device] = openableWithRoster('+994553000002', 'WL-2', '+994700130002');
    $svc = app(WhitelistService::class);
    $svc->enqueue($device, WhitelistAction::Add, '+994553000010');
    $svc->enqueue($device, WhitelistAction::Add, '+994553000011');
    $svc->enqueue($device, WhitelistAction::Remove, '+994553000010');

    $processed = runWhitelistSync();

    expect($processed)->toBe(3)
        ->and(WhitelistChange::query()->where('device_id', $device->id)->where('status', 'pending')->count())->toBe(0)
        ->and(WhitelistChange::query()->where('device_id', $device->id)->where('status', 'synced')->count())->toBe(3);
});

it('completes a whole-device clear change', function () {
    [$device] = openableWithRoster('+994553000003', 'WL-3', '+994700130003');
    app(WhitelistService::class)->enqueue($device, WhitelistAction::Clear, WhitelistService::CLEAR_MARKER);

    runWhitelistSync();

    $change = WhitelistChange::query()->where('device_id', $device->id)->firstOrFail();
    expect($change->status)->toBe(WhitelistChangeStatus::Synced);
});

it('does not reprocess an already-synced change', function () {
    [$device] = openableWithRoster('+994553000004', 'WL-4', '+994700130004');
    app(WhitelistService::class)->enqueue($device, WhitelistAction::Add, '+994553000004');

    expect(runWhitelistSync())->toBe(1)
        ->and(runWhitelistSync())->toBe(0); // nothing left pending
});
