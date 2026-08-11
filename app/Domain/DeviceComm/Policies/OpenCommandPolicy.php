<?php

namespace App\Domain\DeviceComm\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\DeviceComm\Models\OpenCommand;
use App\Domain\Users\Models\User;

/**
 * A user may view / give feedback on only their own open commands; admins may view any. Non-owners
 * get 404 (no existence leak), enforced at the controller.
 */
class OpenCommandPolicy
{
    public function view(mixed $actor, OpenCommand $command): bool
    {
        if ($actor instanceof AdminUser) {
            return true;
        }

        return $actor instanceof User && (int) $command->user_id === (int) $actor->getKey();
    }

    public function submitFeedback(mixed $actor, OpenCommand $command): bool
    {
        return $actor instanceof User && (int) $command->user_id === (int) $actor->getKey();
    }
}
