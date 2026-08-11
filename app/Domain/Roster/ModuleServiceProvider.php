<?php

namespace App\Domain\Roster;

use Illuminate\Support\ServiceProvider;

/**
 * Roster & Invitations (Backend Arch §14.5): device_users roster, history,
 * invitation flow, server-side capacity enforcement (HIGH-05), burst guard (HIGH-01).
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
