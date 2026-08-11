<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Broadcasting (Reverb). Channel auth routes are registered via withRouting(channels:)
 * in bootstrap/app.php; the realtime channels (private-user.{id}, private-device.{id},
 * Tech Spec §6.4) and their authorization callbacks are defined in routes/channels.php
 * as their owning modules land. Kept as an explicit provider per Backend Arch §3.
 */
class BroadcastServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        //
    }
}
