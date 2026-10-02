<?php

namespace App\Domain\Applications\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Applications\Enums\IndividualApplicationStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Physical-person (private yard) application — IMPLEMENTATION_PLAN M10 / B9. */
class IndividualApplication extends Model
{
    protected $table = 'individual_applications';

    protected $guarded = ['id', 'is_open'];

    protected function casts(): array
    {
        return [
            'status' => IndividualApplicationStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'status_changed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function handledBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'handled_by_admin_id');
    }
}
