<?php

namespace App\Domain\Roster\Models;

use App\Domain\Roster\Enums\FamilyLinkStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Head → member family relation (IMPLEMENTATION_PLAN §4.2 / M3). Device access is granted separately per
 * device (device_users.family_link_id → this row); billing stays the member's own subscription.
 */
class FamilyLink extends Model
{
    protected $table = 'family_links';

    protected $guarded = ['id', 'is_active'];

    protected function casts(): array
    {
        return [
            'status' => FamilyLinkStatus::class,
            'linked_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function head(): BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(User::class, 'member_user_id');
    }

    /** The device rows granted through this link. */
    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class, 'family_link_id');
    }

    public function isActive(): bool
    {
        return $this->status === FamilyLinkStatus::Active;
    }
}
