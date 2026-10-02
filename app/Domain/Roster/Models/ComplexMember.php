<?php

namespace App\Domain\Roster\Models;

use App\Domain\Admin\Models\Complex;
use App\Domain\Roster\Enums\ComplexMemberStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A resident's membership of a residential complex (IMPLEMENTATION_PLAN §4.1 / M2). Independent of device
 * access: browsing/subscribing to the complex's shared devices requires an active membership; holding a
 * roster row does not imply one (family members never get one).
 */
class ComplexMember extends Model
{
    protected $table = 'complex_members';

    protected $guarded = ['id', 'is_active'];

    protected function casts(): array
    {
        return [
            'status' => ComplexMemberStatus::class,
            'joined_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
