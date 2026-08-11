<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SimOperator extends Model
{
    protected $table = 'sim_operators';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'sim_operator_id');
    }
}
