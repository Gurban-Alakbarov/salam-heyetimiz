<?php

namespace App\Domain\Payments\Models;

use App\Domain\Payments\Enums\CardTokenStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardToken extends Model
{
    protected $table = 'card_tokens';

    protected $guarded = ['id'];

    protected $hidden = ['bank_token_encrypted'];

    protected function casts(): array
    {
        return [
            'bank_token_encrypted' => 'encrypted',
            'status' => CardTokenStatus::class,
            'expiry_month' => 'integer',
            'expiry_year' => 'integer',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
