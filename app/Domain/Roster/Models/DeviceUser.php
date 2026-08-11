<?php

namespace App\Domain\Roster\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\DeviceUserStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Roster association (device, user, role). The subscription belongs to this row
 * via device_user_id (R-DOM-03). Active-uniqueness is enforced by the STORED
 * generated is_active column (R-DOM-10). `is_active` is read-only (DB-generated).
 */
class DeviceUser extends Model
{
    protected $table = 'device_users';

    protected $guarded = ['id'];

    /** is_active is a DB-generated stored column; never written from PHP. */
    protected $appends = [];

    protected function casts(): array
    {
        return [
            'role' => DeviceUserRole::class,
            'status' => DeviceUserStatus::class,
            'last_open_at' => 'datetime',
            'revoked_at' => 'datetime',
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
