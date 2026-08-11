<?php

namespace App\Domain\Audit;

use App\Domain\Audit\Services\AuditLogger;
use App\Support\Audit\AuditableEvent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Audit (Backend Arch §14.10): append-only forensic log. A single wildcard listener records every domain
 * event that implements AuditableEvent — login, roster, device, ownership, whitelist, etc. — with zero
 * per-event wiring. Explicit admin actions (impersonation, admin CRUD, settings) call AuditLogger directly.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Event::listen('*', function (string $eventName, array $data): void {
            $event = $data[0] ?? null;
            if ($event instanceof AuditableEvent) {
                app(AuditLogger::class)->fromEvent($event);
            }
        });
    }
}
