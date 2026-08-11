<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\OtpPurpose;
use Illuminate\Database\Eloquent\Model;

/**
 * One-time codes for phone verification. Stored hashed; never logged (R-SEC-03).
 */
class Otp extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'otps';

    protected $guarded = ['id'];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'purpose' => OtpPurpose::class,
            'attempts' => 'integer',
            'max_attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
