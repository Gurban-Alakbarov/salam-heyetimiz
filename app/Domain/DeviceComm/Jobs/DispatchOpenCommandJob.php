<?php

namespace App\Domain\DeviceComm\Jobs;

use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\CommandDispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Drains a queued open command off the request path onto the `device-comm` queue (the DeviceComm
 * Horizon supervisor, alongside WhitelistSyncJob), then hands it to the dispatcher (which resolves the
 * driver and runs the attempt + fallback). Carries the command id only (open_commands is partitioned).
 * A missing/terminal command is a no-op (idempotent).
 */
class DispatchOpenCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $commandId)
    {
        $this->onQueue('device-comm');
    }

    public function handle(CommandDispatcher $dispatcher): void
    {
        $command = OpenCommand::query()->find($this->commandId);

        if ($command !== null) {
            $dispatcher->dispatch($command);
        }
    }
}
