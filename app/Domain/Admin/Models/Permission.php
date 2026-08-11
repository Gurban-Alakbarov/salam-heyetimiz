<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/** A catalog permission row (permissions table). The key is the stable identifier checked everywhere. */
class Permission extends Model
{
    protected $table = 'permissions';

    protected $guarded = ['id'];
}
