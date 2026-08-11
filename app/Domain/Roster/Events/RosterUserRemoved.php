<?php

namespace App\Domain\Roster\Events;

use App\Domain\Devices\Models\Device;
use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A resident was removed (revoked) from a device roster. DeviceComm reacts to this to enqueue a
 * whitelist removal into the device outbox (R-GSM-07).
 */
class RosterUserRemoved implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly Device $device,
        public readonly int $userId,
    ) {}

    public function auditAction(): string
    {
        return 'roster.user_removed';
    }

    public function auditPayload(): array
    {
        return [
            'device_id' => $this->device->getKey(),
            'user_id' => $this->userId,
        ];
    }
}
