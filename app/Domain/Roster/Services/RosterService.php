<?php

namespace App\Domain\Roster\Services;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Enums\RosterEventType;
use App\Domain\Roster\Events\RosterUserAdded;
use App\Domain\Roster\Events\RosterUserRemoved;
use App\Domain\Roster\Exceptions\CannotRemoveOwnerException;
use App\Domain\Roster\Exceptions\DeviceNotAssignedException;
use App\Domain\Roster\Exceptions\RosterMemberNotFoundException;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Models\DeviceUserHistory;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use App\Support\Time\Clock;
use Illuminate\Support\Facades\DB;

/**
 * Roster membership for residents who share a barrier (R-DOM-11). Adds/removes non-owner members on an
 * assigned device, append-logs every change to device_user_history, and emits RosterUserAdded/Removed so
 * DeviceComm keeps the whitelist outbox in sync (R-GSM-07). Owner binding/transfer lives in
 * DeviceOwnershipService; this service never re-owners a device.
 */
final class RosterService
{
    public function __construct(private readonly Clock $clock) {}

    /**
     * Add (or re-activate) a resident on an assigned device. Idempotent: an already-active member is
     * returned unchanged. Throws if the device has no owner — you cannot roster a barrier nobody owns.
     */
    public function addMember(Device $device, User $user, DeviceUserRole $role, ActorKind $actorKind, ?int $actorId): DeviceUser
    {
        if ($device->owner_user_id === null) {
            throw new DeviceNotAssignedException();
        }

        return DB::transaction(function () use ($device, $user, $role, $actorKind, $actorId): DeviceUser {
            /** @var DeviceUser|null $active */
            $active = DeviceUser::query()
                ->where('device_id', $device->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Active->value)
                ->first();

            if ($active !== null) {
                return $active; // idempotent — already on the roster
            }

            /** @var DeviceUser|null $revoked */
            $revoked = DeviceUser::query()
                ->where('device_id', $device->getKey())
                ->where('user_id', $user->getKey())
                ->where('status', DeviceUserStatus::Revoked->value)
                ->orderByDesc('id')
                ->first();

            if ($revoked !== null) {
                $revoked->forceFill([
                    'role' => $role->value,
                    'status' => DeviceUserStatus::Active->value,
                    'revoked_at' => null,
                    'revoked_by_admin_id' => null,
                    'revoked_by_user_id' => null,
                    'added_by_admin_id' => $actorKind === ActorKind::Admin ? $actorId : null,
                    'added_by_user_id' => $actorKind === ActorKind::User ? $actorId : null,
                ])->save();
                $row = $revoked;
                $this->logHistory($device, $user, RosterEventType::ReAdded, null, $role, $actorKind, $actorId);
            } else {
                /** @var DeviceUser $row */
                $row = DeviceUser::query()->create([
                    'device_id' => $device->getKey(),
                    'user_id' => $user->getKey(),
                    'role' => $role->value,
                    'status' => DeviceUserStatus::Active->value,
                    'added_by_admin_id' => $actorKind === ActorKind::Admin ? $actorId : null,
                    'added_by_user_id' => $actorKind === ActorKind::User ? $actorId : null,
                ]);
                $this->logHistory($device, $user, RosterEventType::Added, null, $role, $actorKind, $actorId);
            }

            RosterUserAdded::dispatch($device, (int) $user->getKey(), $role->value);

            return $row;
        });
    }

    /** Revoke a resident's roster row (whitelist removal follows via the event). Owners cannot be removed here. */
    public function removeMember(Device $device, User $user, ActorKind $actorKind, ?int $actorId): void
    {
        /** @var DeviceUser|null $row */
        $row = DeviceUser::query()
            ->where('device_id', $device->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', DeviceUserStatus::Active->value)
            ->first();

        if ($row === null) {
            throw new RosterMemberNotFoundException();
        }

        if ($row->role === DeviceUserRole::Owner) {
            throw new CannotRemoveOwnerException();
        }

        $fromRole = $row->role;

        DB::transaction(function () use ($device, $user, $row, $fromRole, $actorKind, $actorId): void {
            $row->forceFill([
                'status' => DeviceUserStatus::Revoked->value,
                'revoked_at' => $this->clock->now(),
                'revoked_by_admin_id' => $actorKind === ActorKind::Admin ? $actorId : null,
                'revoked_by_user_id' => $actorKind === ActorKind::User ? $actorId : null,
            ])->save();

            $this->logHistory($device, $user, RosterEventType::Revoked, $fromRole, null, $actorKind, $actorId);

            RosterUserRemoved::dispatch($device, (int) $user->getKey());
        });
    }

    private function logHistory(Device $device, User $user, RosterEventType $event, ?DeviceUserRole $fromRole, ?DeviceUserRole $toRole, ActorKind $actorKind, ?int $actorId): void
    {
        DeviceUserHistory::query()->create([
            'device_id' => (int) $device->getKey(),
            'user_id' => (int) $user->getKey(),
            'event' => $event->value,
            'from_role' => $fromRole?->value,
            'to_role' => $toRole?->value,
            'actor_kind' => $actorKind->value,
            'actor_id' => $actorId,
        ]);
    }
}
