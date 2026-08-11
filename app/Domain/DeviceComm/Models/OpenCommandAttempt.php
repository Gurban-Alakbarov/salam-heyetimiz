<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-attempt dispatch history for a parent open command (DB Arch §6.1.1). Primary = attempt_no 1,
 * fallback = attempt_no 2 (R-GSM-04). started_at via DB default; no updated_at.
 */
class OpenCommandAttempt extends Model
{
    public $timestamps = false;

    protected $table = 'open_command_attempts';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'driver' => DriverType::class,
            'state' => OpenCommandState::class,
            'attempt_no' => 'integer',
            'latency_ms' => 'integer',
            'metadata' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function command(): BelongsTo
    {
        return $this->belongsTo(OpenCommand::class, 'open_command_id');
    }
}
