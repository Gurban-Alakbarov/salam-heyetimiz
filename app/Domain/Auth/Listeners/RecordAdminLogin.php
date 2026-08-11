<?php

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\AdminAuthenticated;
use App\Support\Time\Clock;

/** Stamp admin_users.last_login_at/ip on a successful two-factor authentication. */
class RecordAdminLogin
{
    public function __construct(private readonly Clock $clock) {}

    public function handle(AdminAuthenticated $event): void
    {
        $event->admin->forceFill([
            'last_login_at' => $this->clock->now(),
            'last_login_ip' => $event->ip,
        ])->save();
    }
}
