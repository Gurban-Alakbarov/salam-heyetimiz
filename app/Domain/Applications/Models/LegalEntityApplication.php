<?php

namespace App\Domain\Applications\Models;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Applications\Enums\LegalEntityApplicationStatus;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legal-entity (residential complex / building) application — IMPLEMENTATION_PLAN M11 / B9. */
class LegalEntityApplication extends Model
{
    protected $table = 'legal_entity_applications';

    protected $guarded = ['id', 'is_pending'];

    protected function casts(): array
    {
        return [
            'status' => LegalEntityApplicationStatus::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'apartments_count' => 'integer',
            'reviewed_at' => 'datetime',
        ];
    }

    public function applicant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'applicant_user_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class, 'reviewed_by_admin_id');
    }

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }
}
