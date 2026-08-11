<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Auth\Events\AdminLoggedOut;

/**
 * Admin logout (openapi adminLogout). Admin access tokens are stateless 30-minute JWTs, so
 * logout is audited and the client discards the token; true server-side revocation relies on
 * the short TTL (a denylist is a future hardening item — see review).
 */
final class AdminLogout
{
    public function handle(AdminUser $admin): void
    {
        AdminLoggedOut::dispatch((int) $admin->getKey());
    }
}
