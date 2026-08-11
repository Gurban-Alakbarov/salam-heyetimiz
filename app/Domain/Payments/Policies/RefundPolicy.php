<?php

namespace App\Domain\Payments\Policies;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Payments\Models\Order;

/** Refunds are super-admin only (R-PAY-09; OpenAPI x-authorization: super_admin). */
class RefundPolicy
{
    public function create(mixed $actor, Order $order): bool
    {
        return $this->isSuperAdmin($actor);
    }

    public function viewAny(mixed $actor): bool
    {
        // adminListRefunds is open to any admin (no x-authorization in OpenAPI); refund *creation*
        // remains super-admin only via create().
        return $actor instanceof AdminUser;
    }

    private function isSuperAdmin(mixed $actor): bool
    {
        return $actor instanceof AdminUser && $actor->role === AdminRole::SuperAdmin;
    }
}
