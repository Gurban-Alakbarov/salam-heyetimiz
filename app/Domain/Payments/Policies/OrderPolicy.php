<?php

namespace App\Domain\Payments\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Payments\Models\Order;
use App\Domain\Users\Models\User;

/**
 * Order authorization. A mobile user may only act on their own orders (R-PAY-12); admins may view
 * any order. The return-URL `orderId` is therefore never sufficient to read another user's order.
 */
class OrderPolicy
{
    public function view(mixed $actor, Order $order): bool
    {
        if ($actor instanceof AdminUser) {
            return true;
        }

        return $actor instanceof User && (int) $order->payer_user_id === (int) $actor->getKey();
    }

    public function create(mixed $actor): bool
    {
        return $actor instanceof User;
    }

    public function recheck(mixed $actor, Order $order): bool
    {
        return $this->view($actor, $order);
    }
}
