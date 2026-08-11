<?php

namespace App\Domain\DeviceComm\Jobs;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Enums\WhitelistChangeStatus;
use App\Domain\DeviceComm\Models\WhitelistChange;
use App\Domain\DeviceComm\Services\DriverResolver;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drains the whitelist/provisioning outbox (R-GSM-07): due `pending` changes are applied per device in
 * (priority ASC, seq ASC) order via the resolved driver, retried (max 5, backoff). Under the v1.2
 * Traccar model device-side whitelist is a no-op (server-side authorisation), so add/remove/clear
 * complete immediately as `synced`; the outbox remains the audit + future-credential channel.
 */
class WhitelistSyncJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onQueue('device-comm');
    }

    public function handle(DriverResolver $drivers, Clock $clock): int
    {
        $now = $clock->now();
        $processed = 0;

        WhitelistChange::query()
            ->where('status', WhitelistChangeStatus::Pending->value)
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', $now))
            ->orderBy('device_id')
            ->orderBy('priority')
            ->orderBy('seq')
            ->get()
            ->each(function (WhitelistChange $change) use ($drivers, $now, &$processed): void {
                $device = Device::query()->withTrashed()->find($change->device_id);
                $driver = $device !== null ? $drivers->resolve($device) : null;

                if ($device === null || $driver === null) {
                    $this->fail($change, $now, 'driver_unavailable');

                    return;
                }

                $result = match ($change->action) {
                    WhitelistAction::Add => $driver->whitelistAdd($device, $change->phone),
                    WhitelistAction::Remove => $driver->whitelistRemove($device, $change->phone),
                    WhitelistAction::Clear => \App\Domain\DeviceComm\DTOs\DriverSyncResult::ok(),
                };

                if ($result->success) {
                    $change->forceFill([
                        'status' => WhitelistChangeStatus::Synced->value,
                        'synced_at' => $now,
                        'last_attempt_at' => $now,
                    ])->save();
                    $processed++;

                    return;
                }

                $this->fail($change, $now, $result->failureReason ?? 'sync_failed');
            });

        return $processed;
    }

    private function fail(WhitelistChange $change, \Carbon\CarbonImmutable $now, string $error): void
    {
        $attempts = (int) $change->attempt_count + 1;
        $terminal = $attempts >= (int) $change->max_attempts;

        $change->forceFill([
            'attempt_count' => $attempts,
            'last_error' => $error,
            'last_attempt_at' => $now,
            'status' => $terminal ? WhitelistChangeStatus::Failed->value : WhitelistChangeStatus::Pending->value,
            'next_attempt_at' => $terminal ? null : $now->addSeconds(60 * $attempts),
        ])->save();
    }
}
