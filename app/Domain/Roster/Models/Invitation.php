<?php

namespace App\Domain\Roster\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Devices\Models\Device;
use App\Domain\Roster\Enums\DeviceUserRole;
use App\Domain\Roster\Enums\InvitationKind;
use App\Domain\Roster\Enums\InvitationPayer;
use App\Domain\Roster\Enums\InvitationStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Invitation (DB Arch §3.4; generalised in IMPLEMENTATION_PLAN B3): `family_member` (join a head's devices)
 * or `complex_resident` (join a residential complex). Only token_hash is stored. device_users / complex
 * membership are created only on acceptance (B6/B8). linked_order_id FK to orders is added in batch 11
 * (orders is batch 05) — the column exists here without that constraint.
 */
class Invitation extends Model
{
    protected $table = 'invitations';

    protected $guarded = ['id'];

    protected $hidden = ['token', 'token_hash'];

    protected function casts(): array
    {
        return [
            'kind' => InvitationKind::class,
            'role' => DeviceUserRole::class,
            'payer' => InvitationPayer::class,
            'status' => InvitationStatus::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'last_sent_at' => 'datetime',
            'revoked_at' => 'datetime',
            'send_count' => 'integer',
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

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function invitedByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'invited_by_admin_id');
    }

    /** Live = pending and not past expiry (expiry is also swept to `expired` hourly). */
    public function isLive(\Carbon\CarbonInterface $now): bool
    {
        return $this->status === InvitationStatus::Pending && $this->expires_at->greaterThan($now);
    }

    public function invitee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invitee_user_id');
    }
}
