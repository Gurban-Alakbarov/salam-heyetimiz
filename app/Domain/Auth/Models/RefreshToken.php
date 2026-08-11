<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\RefreshTokenRevocationReason;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Long-lived rotated refresh token (60 days), SHA-256 hashed (R-SEC-02).
 */
class RefreshToken extends Model
{
    public $timestamps = false;

    protected $table = 'refresh_tokens';

    protected $guarded = ['id'];

    protected $hidden = ['token_hash'];

    protected function casts(): array
    {
        return [
            'revocation_reason' => RefreshTokenRevocationReason::class,
            'issued_at' => 'datetime',
            'last_used_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function userDevice(): BelongsTo
    {
        return $this->belongsTo(UserDevice::class, 'user_device_id');
    }
}
