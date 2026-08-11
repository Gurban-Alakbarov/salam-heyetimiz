<?php

namespace App\Domain\Auth\Models;

use App\Domain\Auth\Enums\AuthActorKind;
use App\Domain\Auth\Enums\AuthOutcome;
use Illuminate\Database\Eloquent\Model;

/**
 * Append-only brute-force / abuse detection log (DB Arch §1.6).
 */
class AuthAttempt extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'auth_attempts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'actor_kind' => AuthActorKind::class,
            'outcome' => AuthOutcome::class,
            'created_at' => 'datetime',
        ];
    }
}
