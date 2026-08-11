<?php

namespace App\Domain\Users\Models;

use App\Domain\Auth\Models\UserDevice;
use App\Domain\Devices\Models\Device;
use App\Domain\Privacy\Models\UserConsent;
use App\Domain\Roster\Models\DeviceUser;
use App\Domain\Users\Enums\UserStatus;
use App\Support\Enums\Locale;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Mobile end-user identity. Phone (E.164) is canonical (R-DOM-01). Soft-delete
 * anonymises PII immediately (R-DOM-02 / HIGH-12).
 */
class User extends Authenticatable
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = ['id'];

    // `password` is a reserved nullable column (future Security/password-login feature); kept hidden so
    // it can never serialise once a hash is set. Unused by the registration module today.
    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'preferred_language' => Locale::class,
            'status' => UserStatus::class,
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }

    public function userDevices(): HasMany
    {
        return $this->hasMany(UserDevice::class, 'user_id');
    }

    public function deviceUsers(): HasMany
    {
        return $this->hasMany(DeviceUser::class, 'user_id');
    }

    public function ownedDevices(): HasMany
    {
        return $this->hasMany(Device::class, 'owner_user_id');
    }

    public function consents(): HasMany
    {
        return $this->hasMany(UserConsent::class, 'user_id');
    }
}
