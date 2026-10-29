<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceAssignmentData;
use App\Domain\Devices\Enums\DeviceOwnershipMode;
use App\Domain\Devices\Exceptions\DeviceIsComplexModeException;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceOwnershipService;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Actions\EnsurePayableSubscription;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountEmailService;
use App\Support\Enums\ActorKind;
use Illuminate\Support\Facades\DB;

/**
 * Technical-mode ownership assignment (techAssignDevice). Resolves (or provisions) the owner by phone
 * — phone is canonical (R-DOM-01) — then binds them and activates the device.
 */
final class AssignDeviceOwner
{
    public function __construct(
        private readonly DeviceOwnershipService $ownership,
        private readonly AccountEmailService $emails,
        private readonly EnsurePayableSubscription $payable,
    ) {}

    public function handle(Device $device, DeviceAssignmentData $data, AdminUser $admin): Device
    {
        // Y6 — a complex-mode device never has an owner; checked before anyone is provisioned.
        if ($device->ownership_mode === DeviceOwnershipMode::Complex) {
            throw new DeviceIsComplexModeException;
        }

        return DB::transaction(function () use ($device, $data, $admin): Device {
            /** @var User $owner */
            $owner = User::query()->firstOrCreate(
                ['phone' => $data->ownerPhone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => UserStatus::Active->value],
            );
            // K1 — the owner registers with this email later; the email OTP is what proves the account is theirs.
            $this->emails->recordIfUnverified($owner, $data->ownerEmail);

            $device = $this->assign($device, $owner, $data, $admin);

            // 2026-10-30 — the owner always has a way to pay in the app (or the installer makes it free).
            $row = DeviceUser::query()->where('device_id', $device->getKey())->where('user_id', $owner->getKey())
                ->where('status', DeviceUserStatus::Active->value)->first();
            if ($row !== null) {
                $this->payable->handle($row);
            }

            return $device;
        });
    }

    private function assign(Device $device, User $owner, DeviceAssignmentData $data, AdminUser $admin): Device
    {
        return $this->ownership->assignOwner(
            $device,
            $owner,
            [
                'region_id' => $data->regionId,
                'location_label' => $data->locationLabel,
                'latitude' => $data->latitude,
                'longitude' => $data->longitude,
            ],
            ActorKind::Admin,
            (int) $admin->getKey(),
            'technical',
        );
    }
}
