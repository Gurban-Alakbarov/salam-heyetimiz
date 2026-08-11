<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Ownership transferred to a new user (admin-mediated, R-DOM-12). DeviceComm reprograms the whitelist
 * in batch 09; this carries the before/after owners and whether prior members were kept.
 */
class DeviceTransferred implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly ?int $previousOwnerUserId,
        public readonly int $newOwnerUserId,
        public readonly bool $keptExistingUsers,
        public readonly ?string $reason = null,
    ) {}

    public function auditAction(): string
    {
        return 'device.transferred';
    }

    public function auditPayload(): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'previous_owner_user_id' => $this->previousOwnerUserId,
            'new_owner_user_id' => $this->newOwnerUserId,
            'kept_existing_users' => $this->keptExistingUsers,
            'reason' => $this->reason,
        ];
    }
}
