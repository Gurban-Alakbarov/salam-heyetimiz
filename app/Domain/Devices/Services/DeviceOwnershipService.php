<?php

namespace App\Domain\Devices\Services;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Events\DeviceAssigned;
use App\Domain\Devices\Events\DeviceTransferred;
use App\Domain\Devices\Exceptions\DeviceAlreadyAssignedException;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\RosterEventType;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\DeviceUserHistory;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Device ownership + roster transitions (R-DOM-11/12/13). Binding an owner activates the device and
 * seeds the owner's roster row; transfer re-owners it (admin-mediated) and optionally retains existing
 * members. Every roster mutation is append-logged to device_user_history (R-DOM-11). Whitelist
 * programming is DeviceComm's reaction to the emitted events (batch 09) — not done here.
 */
final class DeviceOwnershipService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Bind an owner to an unassigned device (technical assignment or purchase). Activates the device
     * and creates the owner roster row.
     *
     * @param  array<string, mixed>  $placement  region_id / location_label / latitude / longitude (nullable)
     */
    public function assignOwner(Device $device, User $owner, array $placement, ActorKind $actorKind, ?int $actorId, string $via): Device
    {
        if ($device->owner_user_id !== null) {
            throw new DeviceAlreadyAssignedException();
        }

        return DB::transaction(function () use ($device, $owner, $placement, $actorKind, $actorId, $via): Device {
            $device->forceFill(array_merge(
                $this->presentPlacement($placement),
                [
                    'owner_user_id' => $owner->getKey(),
                    'status' => DeviceStatus::Active->value,
                    'activated_at' => $device->activated_at ?? $this->clock->now(),
                ],
            ))->save();

            $this->addRosterRow($device, $owner, DeviceUserRole::Owner, $actorKind, $actorId, RosterEventType::Added);

            DeviceAssigned::dispatch($device, (int) $owner->getKey(), $via);

            return $device;
        });
    }

    /**
     * Re-owner a device to another user (R-DOM-12). The previous owner's roster row is revoked; other
     * members are kept or revoked per $keepExistingUsers. Admin-only at the controller layer.
     */
    public function transfer(Device $device, User $newOwner, string $reason, bool $keepExistingUsers, ?AdminUser $admin): Device
    {
        $previousOwnerId = $device->owner_user_id !== null ? (int) $device->owner_user_id : null;
        $actorId = $admin?->getKey();

        return DB::transaction(function () use ($device, $newOwner, $reason, $keepExistingUsers, $previousOwnerId, $actorId): Device {
            $activeRows = DeviceUser::query()
                ->where('device_id', $device->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->get();

            foreach ($activeRows as $row) {
                $isPriorOwner = $previousOwnerId !== null && (int) $row->user_id === $previousOwnerId;
                $isNewOwner = (int) $row->user_id === (int) $newOwner->getKey();

                // Always revoke the prior owner; revoke everyone if members are not being kept.
                if ($isPriorOwner || (! $keepExistingUsers && ! $isNewOwner)) {
                    $this->revokeRosterRow($row, ActorKind::Admin, $actorId);
                }
            }

            $device->forceFill([
                'owner_user_id' => $newOwner->getKey(),
                'status' => DeviceStatus::Active->value,
            ])->save();

            $this->addRosterRow($device, $newOwner, DeviceUserRole::Owner, ActorKind::Admin, $actorId, RosterEventType::Added);

            DeviceTransferred::dispatch($device, $previousOwnerId, (int) $newOwner->getKey(), $keepExistingUsers, $reason);

            return $device;
        });
    }

    private function addRosterRow(Device $device, User $user, DeviceUserRole $role, ActorKind $actorKind, ?int $actorId, RosterEventType $event): void
    {
        /** @var DeviceUser|null $existing */
        $existing = DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->first();

        if ($existing !== null) {
            if ($existing->role !== $role) {
                $fromRole = $existing->role;
                $existing->forceFill(['role' => $role->value])->save();
                $this->logHistory((int) $device->getKey(), (int) $user->getKey(), RosterEventType::RoleChanged, $fromRole, $role, $actorKind, $actorId);
            }

            return;
        }

        DeviceUser::query()->create([
            'device_id' => $device->getKey(),
            'user_id' => $user->getKey(),
            'role' => $role->value,
            'status' => DeviceUserStatus::Active->value,
            'added_by_admin_id' => $actorKind === ActorKind::Admin ? $actorId : null,
            'added_by_user_id' => $actorKind === ActorKind::User ? $actorId : null,
        ]);

        $this->logHistory((int) $device->getKey(), (int) $user->getKey(), $event, null, $role, $actorKind, $actorId);
    }

    private function revokeRosterRow(DeviceUser $row, ActorKind $actorKind, ?int $actorId): void
    {
        $fromRole = $row->role;
        $deviceId = (int) $row->device_id;
        $userId = (int) $row->user_id;

        $row->forceFill([
            'status' => DeviceUserStatus::Revoked->value,
            'revoked_at' => $this->clock->now(),
            'revoked_by_admin_id' => $actorKind === ActorKind::Admin ? $actorId : null,
            'revoked_by_user_id' => $actorKind === ActorKind::User ? $actorId : null,
        ])->save();

        $this->logHistory($deviceId, $userId, RosterEventType::Revoked, $fromRole, null, $actorKind, $actorId);
    }

    private function logHistory(int $deviceId, int $userId, RosterEventType $event, ?DeviceUserRole $fromRole, ?DeviceUserRole $toRole, ActorKind $actorKind, ?int $actorId): void
    {
        DeviceUserHistory::query()->create([
            'device_id' => $deviceId,
            'user_id' => $userId,
            'event' => $event->value,
            'from_role' => $fromRole?->value,
            'to_role' => $toRole?->value,
            'actor_kind' => $actorKind->value,
            'actor_id' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $placement
     * @return array<string, mixed>
     */
    private function presentPlacement(array $placement): array
    {
        return array_filter(
            [
                'region_id' => $placement['region_id'] ?? null,
                'location_label' => $placement['location_label'] ?? null,
                'latitude' => $placement['latitude'] ?? null,
                'longitude' => $placement['longitude'] ?? null,
            ],
            static fn ($value): bool => $value !== null,
        );
    }
}
