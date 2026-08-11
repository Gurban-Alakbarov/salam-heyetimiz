<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Maps a platform device to its Traccar identity (v1.2). */
class TraccarDevice extends Model
{
    protected $table = 'traccar_devices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'traccar_id' => 'integer',
            'registered_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
