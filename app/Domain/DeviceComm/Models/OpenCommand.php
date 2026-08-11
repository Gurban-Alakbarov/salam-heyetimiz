<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\CommandDirection;
use App\Domain\DeviceComm\Enums\OpenCommandSource;
use App\Domain\DeviceComm\Enums\OpenCommandState;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Subscriptions\Models\Subscription;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Every attempted open (DB Arch §6.1) — the most write-heavy, append-style table. RANGE-partitioned
 * by partition_key (YYYYMM); the service sets partition_key + requested_at explicitly. No updated_at.
 */
class OpenCommand extends Model
{
    public $timestamps = false;

    protected $table = 'open_commands';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'state' => OpenCommandState::class,
            'source' => OpenCommandSource::class,
            'direction' => CommandDirection::class,
            'driver' => DriverType::class,
            'attempts' => 'integer',
            'latency_ms' => 'integer',
            'metadata' => 'array',
            'requested_at' => 'datetime',
            'dispatched_at' => 'datetime',
            'completed_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function deviceUser(): BelongsTo
    {
        return $this->belongsTo(DeviceUser::class, 'device_user_id');
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class, 'subscription_id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(OpenCommandAttempt::class, 'open_command_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(OpenCommandFeedback::class, 'open_command_id');
    }
}
