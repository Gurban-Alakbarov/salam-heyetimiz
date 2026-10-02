<?php

namespace App\Domain\Devices\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\ComplexMembershipService;
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

        if ($this->activeRosterRow($device, $actor) !== null) {
            return true;
        }

        // A complex device is visible to the residents of its complex (browse → subscribe, B4/B7). Family
        // members are not complex members, so they only ever see the devices their roster rows grant.
        return $this->isComplexResidentOf($device, $actor);
    }

    /**
     * May the user start a subscription for this device themselves (B4)? Only a COMPLEX device that is active,
     * belongs to a complex the user is an active resident of. Private devices keep their owner/admin roster.
     */
    public function subscribe(mixed $actor, Device $device): bool
    {
        return $actor instanceof User
            && $device->ownership_mode === DeviceOwnershipMode::Complex
            && $device->status === DeviceStatus::Active
            && $this->isComplexResidentOf($device, $actor);
    }

    /**
     * May the user invite / manage family members for this device (family head — B8)? Private: the owner only.
     * Complex: a resident holding their OWN active roster row (not one granted via a family link). A family
     * member can never manage users (BR-4).
     */
    public function manageFamily(mixed $actor, Device $device): bool
    {
        if (! $actor instanceof User) {
            return false;
        }

        if ($device->ownership_mode !== DeviceOwnershipMode::Complex) {
            return (int) $device->owner_user_id === (int) $actor->getKey();
        }

        $row = $this->activeRosterRow($device, $actor);

        return $row !== null && $row->family_link_id === null && $this->isComplexResidentOf($device, $actor);
    }

    private function activeRosterRow(Device $device, User $actor): ?DeviceUser
    {
        return DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_id', $actor->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->first();
    }

    private function isComplexResidentOf(Device $device, User $actor): bool
    {
        return $device->ownership_mode === DeviceOwnershipMode::Complex
            && $device->complex_id !== null
            && app(ComplexMembershipService::class)->isMember((int) $device->complex_id, (int) $actor->getKey());
    }

    /**
     * A mobile user may CONFIGURE a device (its geofence) only if they OWN it — owner-only, NOT roster
     * members (GEOFENCE-1 D5). Admins may configure any. The request/action restricts this to geofence
     * settings, so it can never change ownership, coordinates, roster, or subscription.
     */
    public function configure(mixed $actor, Device $device): bool
    {
        if ($actor instanceof AdminUser) {
            return true;
        }

        return $actor instanceof User
            && (int) $device->owner_user_id === (int) $actor->getKey();
    }
}
