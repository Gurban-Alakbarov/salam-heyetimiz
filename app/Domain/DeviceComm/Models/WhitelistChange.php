<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\WhitelistAction;
use App\Domain\DeviceComm\Enums\WhitelistChangeStatus;
use App\Domain\Roster\Models\DeviceUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Outbox row for a pending whitelist mutation (DB Arch §6.3). Drained per device in
 * (priority ASC, seq ASC) order by the WhitelistSyncJob (GSM batch). created_at only; no updated_at.
 */
class WhitelistChange extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'whitelist_changes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'action' => WhitelistAction::class,
            'status' => WhitelistChangeStatus::class,
            'priority' => 'integer',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'seq' => 'integer',
            'last_attempt_at' => 'datetime',
            'next_attempt_at' => 'datetime',
            'synced_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function deviceUser(): BelongsTo
    {
        return $this->belongsTo(DeviceUser::class, 'device_user_id');
    }
}
