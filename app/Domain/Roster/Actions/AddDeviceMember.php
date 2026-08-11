<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\DTOs\RosterMemberData;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\DB;

/**
 * Add a resident to a device roster from the admin panel. Resolves (or provisions) the resident by phone
 * — phone is canonical (R-DOM-01) — so "create resident" and "add to barrier" are one admin action. The
 * whole thing is atomic: if the device can't accept residents (not assigned), the provisioned user is
 * rolled back rather than left dangling.
 */
final class AddDeviceMember
{
    public function __construct(private readonly RosterService $roster) {}

    public function handle(Device $device, RosterMemberData $data, AdminUser $admin): DeviceUser
    {
        return DB::transaction(function () use ($device, $data, $admin): DeviceUser {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['phone' => $data->phone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => UserStatus::Active->value],
            );

            return $this->roster->addMember($device, $user, $data->role, ActorKind::Admin, (int) $admin->getKey());
        });
    }
}
