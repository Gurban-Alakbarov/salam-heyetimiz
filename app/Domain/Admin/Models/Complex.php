<?php

namespace App\Domain\Admin\Models;

use App\Domain\Devices\Models\Device;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A residential complex (yard) — the scope unit for complex_manager admins and a grouping for devices. */
class Complex extends Model
{
    protected $table = 'complexes';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class, 'complex_id');
    }

    public function admins(): HasMany
    {
        return $this->hasMany(AdminUser::class, 'complex_id');
    }
}
