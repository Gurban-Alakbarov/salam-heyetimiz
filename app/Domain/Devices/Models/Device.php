<?php

namespace App\Domain\Devices\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Catalog\Models\DeviceModel;
use App\Domain\Catalog\Models\Region;
use App\Domain\Catalog\Models\SimOperator;
use App\Domain\Devices\Enums\DeviceStatus;
use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Enums\SimStatus;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Physical GSM-controlled access controller (DB Arch §3.1).
 */
class Device extends Model
{
    use SoftDeletes;

    protected $table = 'devices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'driver_type' => DriverType::class,
            'status' => DeviceStatus::class,
            'sim_status' => SimStatus::class,
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'geofence_enabled' => 'boolean',
            'geofence_radius_m' => 'integer',
            'last_online_at' => 'datetime',
            'last_signal_strength' => 'integer',
            'consecutive_offline_diagnostics' => 'integer',
            // whitelist_capacity_used is DEPRECATED — compute on read (R-DOM-14).
            'sim_credit_minor' => 'integer',
            'sim_credit_checked_at' => 'datetime',
            'metadata' => 'array',
            'activated_at' => 'datetime',
            'decommissioned_at' => 'datetime',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(DeviceModel::class, 'device_model_id');
    }

    public function simOperator(): BelongsTo
    {
        return $this->belongsTo(SimOperator::class, 'sim_operator_id');
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function registeredByAdmin(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'registered_by_admin_id');
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class, 'device_id');
    }
}
