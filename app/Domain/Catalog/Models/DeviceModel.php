<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Devices\Enums\DriverType;
use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeviceModel extends Model
{
    protected $table = 'device_models';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'supports_clip' => 'boolean',
            'supports_sms' => 'boolean',
            'supports_mqtt' => 'boolean',
            'default_driver_type' => DriverType::class,
            'fallback_open_driver' => DriverType::class,
            'whitelist_capacity' => 'integer',
            'command_result_confirm' => 'boolean',
            'open_pulse_ms' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'device_model_id');
    }
}
