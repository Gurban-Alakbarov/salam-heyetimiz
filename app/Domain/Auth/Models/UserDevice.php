<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\Platform;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per mobile install (source of FCM push tokens, biometric opt-in).
 */
class UserDevice extends Model
{
    protected $table = 'user_devices';

    protected $guarded = ['id'];

    protected $hidden = ['push_token'];

    protected function casts(): array
    {
        return [
            'platform' => Platform::class,
            'push_token_updated_at' => 'datetime',
            'push_invalid' => 'boolean',
            'biometric_enrolled' => 'boolean',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
