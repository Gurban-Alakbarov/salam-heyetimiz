<?php

namespace App\Domain\DeviceComm\Jobs;

use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\DeviceComm\Services\OpenCommandService;
use App\Support\Time\Clock;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Safety net for the command lifecycle: any command still queued/dispatching past the stale window
 * (well beyond the open SLA — R-GSM-13) is moved to the terminal `expired` state so clients polling
 * /v1/commands/{id} stop waiting and the queue does not accumulate zombies.
 */
class ExpireStaleOpenCommandsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private const STALE_AFTER_SECONDS = 120;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(Clock $clock, OpenCommandService $commands): int
    {
        $cutoff = $clock->now()->subSeconds(self::STALE_AFTER_SECONDS);
        $expired = 0;

        OpenCommand::query()
            ->whereIn('state', [OpenCommandState::Queued->value, OpenCommandState::Dispatching->value])
            ->where('requested_at', '<', $cutoff)
            ->orderBy('id')
            ->chunkById(500, function ($commandsChunk) use ($commands, &$expired): void {
                foreach ($commandsChunk as $command) {
                    $commands->markExpired($command);
                    $expired++;
                }
            });

        return $expired;
    }
}
