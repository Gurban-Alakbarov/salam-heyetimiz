<?php

namespace App\Domain\Devices\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;

/**
 * A mobile user may view a device only if they own it or are an active roster member; admins may
 * view any. Non-members get 404 (no existence leak), enforced at the controller (R-API).
 */
class DevicePolicy
{
    public function view(mixed $actor, Device $device): bool
    {
        if ($actor instanceof AdminUser) {
            return true;
        }

        if (! $actor instanceof User) {
            return false;
        }

        if ((int) $device->owner_user_id === (int) $actor->getKey()) {
            return true;
        }

        return DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_id', $actor->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->exists();
    }
}
