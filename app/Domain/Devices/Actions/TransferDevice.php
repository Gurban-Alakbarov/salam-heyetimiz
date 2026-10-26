<?php

namespace App\Domain\Devices\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\DTOs\DeviceTransferData;
use App\Domain\Devices\Models\Device;
use App\Domain\Devices\Services\DeviceOwnershipService;
use App\Domain\Users\Enums\UserStatus;
use App\Domain\Users\Models\User;
use App\Domain\Users\Services\AccountEmailService;
use Illuminate\Support\Facades\DB;

/** Admin-mediated ownership transfer (adminTransferDevice, R-DOM-12). */
final class TransferDevice
{
    public function __construct(
        private readonly DeviceOwnershipService $ownership,
        private readonly AccountEmailService $emails,
    ) {}

    public function handle(Device $device, DeviceTransferData $data, AdminUser $admin): Device
    {
        return DB::transaction(function () use ($device, $data, $admin): Device {
            /** @var User $newOwner */
            $newOwner = User::query()->firstOrCreate(
                ['phone' => $data->newOwnerPhone],
                ['phone_country' => 'AZ', 'preferred_language' => 'az', 'status' => UserStatus::Active->value],
            );
            // K1 — the new owner registers with this email later; the email OTP proves the account is theirs.
            $this->emails->recordIfUnverified($newOwner, $data->newOwnerEmail);

            return $this->ownership->transfer($device, $newOwner, $data->reason, $data->keepExistingUsers, $admin);
        });
    }
}
