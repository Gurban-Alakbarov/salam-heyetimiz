<?php

namespace App\Domain\Roster\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A resident was added (or re-added) to a device roster. DeviceComm reacts to this to enqueue the
 * resident's phone into the device whitelist outbox (R-GSM-07); this module only sets roster state.
 */
class RosterUserAdded implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly int $userId,
        public readonly string $role,
    ) {}

    public function auditAction(): string
    {
        return 'roster.user_added';
    }

    public function auditPayload(): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'user_id' => $this->userId,
            'role' => $this->role,
        ];
    }
}
