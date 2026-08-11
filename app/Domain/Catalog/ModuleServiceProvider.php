<?php

namespace App\Domain\Catalog;

use Illuminate\Support\ServiceProvider;

/**
 * Catalog / Lookups (Backend Arch §14.3): sim_operators, device_models, regions.
 * Reference data only; read facade with caching lands here.
 */
class ModuleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        //
    }
}
