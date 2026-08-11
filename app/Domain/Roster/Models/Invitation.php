<?php

namespace App\Domain\Roster\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\InvitationPayer;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pending invitation to join a device roster (DB Arch §3.4). device_users is
 * created only on acceptance. linked_order_id FK to orders is added in batch 11
 * (orders is batch 05) — the column exists here without that constraint.
 */
class Invitation extends Model
{
    protected $table = 'invitations';

    protected $guarded = ['id'];

    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'role' => DeviceUserRole::class,
            'payer' => InvitationPayer::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function invitedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_user_id');
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}
