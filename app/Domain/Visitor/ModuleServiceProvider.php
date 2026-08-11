<?php

namespace App\Domain\Visitor;

use App\Domain\DeviceComm\Events\OpenCommandCompleted;
use App\Domain\Visitor\Listeners\IncrementVisitorUsageOnOpenCompleted;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Visitor links — temporary, shareable barrier access (couriers, guests…). Link lifecycle lives in
 * VisitorLinkService; the public open reuses the DeviceComm relay pipeline verbatim (VisitorAccessService).
 * Here we only react to the shared open lifecycle to advance usage_count on a confirmed open.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Event::listen(OpenCommandCompleted::class, IncrementVisitorUsageOnOpenCompleted::class);
    }
}
