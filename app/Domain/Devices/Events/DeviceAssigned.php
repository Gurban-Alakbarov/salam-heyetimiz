<?php

namespace App\Domain\Devices\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A device was bound to an owner (purchase or technical assignment). DeviceComm reacts to this to
 * program the owner into the device whitelist (batch 09); this module only sets ownership state.
 */
class DeviceAssigned implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly int $ownerUserId,
        public readonly string $via, // 'purchase' | 'technical'
    ) {}

    public function auditAction(): string
    {
        return 'device.assigned';
    }

    public function auditPayload(): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'owner_user_id' => $this->ownerUserId,
            'via' => $this->via,
        ];
    }
}
