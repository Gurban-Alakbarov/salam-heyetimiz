<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Domain events and listeners are registered per-module by each
 * ModuleServiceProvider (Backend Arch §8). Cross-cutting reactors (Audit,
 * Notifications) subscribe to the curated event set there — feature code never
 * calls them directly (R-ARCH-07). Kept as an explicit provider per Backend Arch §3.
 */
class EventServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
