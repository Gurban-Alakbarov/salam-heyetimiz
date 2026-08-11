<?php

namespace App\Domain\Visitor\Listeners;

use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\Visitor\Models\VisitorLink;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * A visitor open reached a terminal state. usage_count advances ONLY on a confirmed success, so an
 * offline/failed attempt never burns a one-time link (the courier can retry). Atomic increment — no
 * read-modify-write race. Failures leave the link untouched (the usage row already audits the attempt).
 */
class IncrementVisitorUsageOnOpenCompleted
{
    public function __construct(private readonly Clock $clock) {}

    public function handle(OpenCommandCompleted $event): void
    {
        $command = $event->command;

        if ($command->source !== OpenCommandSource::Visitor || ! $command->state->isSuccess()) {
            return;
        }

        $linkId = data_get($command->metadata, 'visitor_link_id');
        if (! is_numeric($linkId)) {
            return;
        }

        $now = $this->clock->now();

        VisitorLink::query()->whereKey((int) $linkId)->update([
            'usage_count' => DB::raw('usage_count + 1'),
            'last_used_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
