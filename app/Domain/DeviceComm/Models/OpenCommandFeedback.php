<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Optional user-reported actuation confirmation (DB Arch §6.1.2, CRIT-06). One per (command, user).
 */
class OpenCommandFeedback extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'open_command_feedback';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'gate_moved' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(OpenCommand::class, 'open_command_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
