<?php

namespace App\Domain\DeviceComm\Models;

use App\Domain\Devices\Models\Device;
use App\Domain\DeviceComm\Enums\DiagnosticSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Device health report (DB Arch §6.2). RANGE-partitioned by partition_key (YYYYMM); the ingestion
 * service sets partition_key + reported_at. No updated_at.
 */
class DeviceDiagnostic extends Model
{
    public $timestamps = false;

    protected $table = 'device_diagnostics';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'source' => DiagnosticSource::class,
            'online' => 'boolean',
            'signal_strength' => 'integer',
            'battery_level' => 'integer',
            'raw' => 'array',
            'reported_at' => 'datetime',
        ];
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }
}
