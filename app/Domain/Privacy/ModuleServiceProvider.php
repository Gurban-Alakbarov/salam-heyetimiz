<?php

namespace App\Domain\Privacy;

use Illuminate\Support\ServiceProvider;

/**
 * Privacy (Backend Arch §14.11): consents, data-subject requests (export/deletion),
 * anonymisation (immediate on soft-delete — HIGH-12).
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
