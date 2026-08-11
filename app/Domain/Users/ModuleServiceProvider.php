<?php

namespace App\Domain\Users;

use Illuminate\Support\ServiceProvider;

/**
 * Users (Backend Arch §14.2): mobile profile, language, consents link, soft-delete
 * lifecycle with immediate anonymisation (HIGH-12).
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
