<?php

namespace App\Domain\Auth\Listeners;

use App\Domain\Auth\Events\UserAuthenticated;
use App\Support\Time\Clock;

/**
 * Side-effect of a successful mobile authentication: stamp users.last_login_at/ip and the
 * install's last_seen. Kept off the token-issuance path so issuance stays lean (R-ARCH-07).
 */
class RecordSuccessfulUserLogin
{
    public function __construct(private readonly Clock $clock) {}

    public function handle(UserAuthenticated $event): void
    {
        $now = $this->clock->now();

        $event->user->forceFill([
            'last_login_at' => $now,
            'last_login_ip' => $event->ip,
        ])->save();

        $event->device->forceFill([
            'last_seen_at' => $now,
            'last_seen_ip' => $event->ip,
        ])->save();
    }
}
