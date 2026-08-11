<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;

/** Remove a resident from a device roster (admin panel). */
final class RemoveDeviceMember
{
    public function __construct(private readonly RosterService $roster) {}

    public function handle(Device $device, int $userId, AdminUser $admin): void
    {
        /** @var User $user */
        $user = User::query()->findOrFail($userId);

        $this->roster->removeMember($device, $user, ActorKind::Admin, (int) $admin->getKey());
    }
}
