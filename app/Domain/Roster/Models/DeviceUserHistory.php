<?php

namespace App\Domain\Roster\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\RosterEventType;
use App\Domain\Users\Models\User;
use App\Support\Enums\ActorKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only roster change log (DB Arch §3.3): powers "who had access on date X".
 */
class DeviceUserHistory extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'device_user_history';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'event' => RosterEventType::class,
            'from_role' => DeviceUserRole::class,
            'to_role' => DeviceUserRole::class,
            'actor_kind' => ActorKind::class,
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
