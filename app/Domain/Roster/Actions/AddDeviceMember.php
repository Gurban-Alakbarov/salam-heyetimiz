<?php

namespace App\Domain\Roster\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\DTOs\RosterMemberData;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Roster\Services\RosterService;
use App\Domain\Subscriptions\Actions\EnsurePayableSubscription;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountEmailService;
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
    public function __construct(
        private readonly RosterService $roster,
        private readonly AccountEmailService $emails,
        private readonly EnsurePayableSubscription $payable,
    ) {}

    public function handle(Device $device, RosterMemberData $data, AdminUser $admin): DeviceUser
    {
        return DB::transaction(function () use ($device, $data, $admin): DeviceUser {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['phone' => $data->phone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => UserStatus::Active->value],
            );
            // K1 — the resident registers with this email later; the email OTP proves the account is theirs.
            $this->emails->recordIfUnverified($user, $data->email);

            $row = $this->roster->addMember($device, $user, $data->role, ActorKind::Admin, (int) $admin->getKey());
            // 2026-10-30 — always a way to pay in the app (or the admin makes it free with "Abunəlik ver").
            $this->payable->handle($row);

            return $row;
        });
    }
}
