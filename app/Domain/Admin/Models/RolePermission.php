<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A role → permission grant (role_permissions). `role` is the AdminRole enum value. */
class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $guarded = ['id'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
