<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $reason
 * @property string|null $scope_group
 * @property array<string, mixed> $snapshot
 * @property array<int, array<string, mixed>>|null $changes
 */
class SettingsVersion extends Model
{
    public const UPDATED_AT = null; // append-only

    protected $table = 'settings_versions';

    protected $guarded = [];

    protected $casts = [
        'snapshot' => 'array',
        'changes' => 'array',
        'created_at' => 'datetime',
    ];
}
