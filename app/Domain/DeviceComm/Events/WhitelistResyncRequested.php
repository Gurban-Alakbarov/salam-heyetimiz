<?php

namespace App\Domain\DeviceComm\Events;

use App\Support\Audit\AuditableEvent;
use Illuminate\Foundation\Events\Dispatchable;

/** An admin forced a full whitelist resync; `count` change rows were enqueued at priority 10. */
class WhitelistResyncRequested implements AuditableEvent
{
    use Dispatchable;

    public function __construct(
        public readonly int $deviceId,
        public readonly int $adminId,
        public readonly int $count,
    ) {}

    public function auditAction(): string
    {
        return 'device.whitelist_resync_requested';
    }

    public function auditPayload(): array
    {
        return ['device_id' => $this->deviceId, 'admin_id' => $this->adminId, 'changes_enqueued' => $this->count];
    }
}
