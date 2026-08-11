<?php

namespace App\Domain\Admin\Models;

use Illuminate\Database\Eloquent\Model;

/** A runtime key/value setting (admin-tunable). */
class Setting extends Model
{
    protected $table = 'settings';

    protected $guarded = ['id'];
}
