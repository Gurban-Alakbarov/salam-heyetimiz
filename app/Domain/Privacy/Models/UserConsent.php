<?php

namespace App\Domain\Privacy\Models;

use App\Domain\Privacy\Enums\ConsentKind;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only consent ledger (AZ Personal Data Law). One row per grant/revoke
 * (DB Arch §1.7); revocation is a new row, never an update.
 */
class UserConsent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'user_consents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'consent_kind' => ConsentKind::class,
            'granted' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
