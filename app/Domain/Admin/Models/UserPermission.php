<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A per-user permission override (effect = grant | revoke) layered on top of the admin's role template. */
class UserPermission extends Model
{
    protected $table = 'user_permissions';

    protected $guarded = ['id'];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class, 'permission_id');
    }
}
