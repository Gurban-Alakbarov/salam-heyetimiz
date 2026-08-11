<?php

namespace App\Domain\Reporting;

use Illuminate\Support\ServiceProvider;

/**
 * Reporting (Backend Arch §14.12): materialised daily stats + async report jobs
 * (CSV/Parquet to object storage).
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
