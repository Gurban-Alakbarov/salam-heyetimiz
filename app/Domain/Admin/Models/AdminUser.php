<?php

namespace App\Domain\Admin\Models;

use App\Domain\Admin\Authorization\Permission;
use App\Domain\Admin\Authorization\RolePermissionMatrix;
use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\Permission as PermissionModel;
use App\Domain\Admin\Models\RolePermission;
use App\Support\Enums\Locale;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Back-office identity (RBAC: super_admin / technical / operator / finance / support / complex_manager).
 * Disjoint from users (R-DOM-01). 2FA: TOTP + 8 single-use recovery codes (R-SEC-06). Authorization is
 * permission-based: hasPermission() resolves the role's grants from `role_permissions` (DB-driven), with
 * super_admin = the full catalog. complex_manager is additionally scoped to its complex_id.
 */
class AdminUser extends Authenticatable
{
    protected $table = 'admin_users';

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'totp_secret',
        'recovery_codes_hashes',
        'remember_token',
    ];

    /** @var array<int, string>|null per-instance cache of resolved permission keys */
    private ?array $permissionKeyCache = null;

    public function complex(): BelongsTo
    {
        return $this->belongsTo(Complex::class, 'complex_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === AdminRole::SuperAdmin;
    }

    /** The complex this admin is scoped to (complex_manager), or null for global roles. */
    public function complexScopeId(): ?int
    {
        return $this->role->isComplexScoped() && $this->complex_id !== null ? (int) $this->complex_id : null;
    }

    /**
     * Role-template permission keys (the defaults, before per-user overrides). super_admin = the whole
     * catalog; everyone else = their role's `role_permissions` grants (DB-driven, code-matrix fallback when
     * unseeded so the system is never accidentally locked out).
     *
     * @return array<int, string>
     */
    public function roleDefaultKeys(): array
    {
        if ($this->isSuperAdmin()) {
            return Permission::all();
        }

        $keys = RolePermission::query()
            ->where('role_permissions.role', $this->role->value)
            ->join('permissions', 'permissions.id', '=', 'role_permissions.permission_id')
            ->pluck('permissions.key')
            ->all();

        if ($keys === [] && PermissionModel::query()->doesntExist()) {
            $keys = RolePermissionMatrix::forRole($this->role); // unseeded environment fallback
        }

        return $keys;
    }

    /** @return array<int, string> per-user GRANTED (extra) permission keys (super has none — already all) */
    public function grantedKeys(): array
    {
        return $this->overrideKeys('grant');
    }

    /** @return array<int, string> per-user REVOKED permission keys */
    public function revokedKeys(): array
    {
        return $this->overrideKeys('revoke');
    }

    /**
     * @return array<int, string>
     */
    private function overrideKeys(string $effect): array
    {
        if ($this->isSuperAdmin() || $this->getKey() === null) {
            return [];
        }

        return UserPermission::query()
            ->where('user_permissions.admin_user_id', $this->getKey())
            ->where('user_permissions.effect', $effect)
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->pluck('permissions.key')
            ->all();
    }

    /**
     * EFFECTIVE permissions = super → all; otherwise (role defaults ∪ granted) \ revoked. This is the one
     * authorization source — every check (middleware, /me, sidebar) reads it. Cached per request; resolved
     * from the DB so grants/revokes take effect on the next request (next login on the client). R-SEC RBAC.
     *
     * @return array<int, string>
     */
    public function permissionKeys(): array
    {
        if ($this->permissionKeyCache !== null) {
            return $this->permissionKeyCache;
        }

        if ($this->isSuperAdmin()) {
            return $this->permissionKeyCache = Permission::all();
        }

        $effective = array_values(array_diff(
            array_unique([...$this->roleDefaultKeys(), ...$this->grantedKeys()]),
            $this->revokedKeys(),
        ));

        return $this->permissionKeyCache = $effective;
    }

    public function hasPermission(string $key): bool
    {
        return $this->isSuperAdmin() || in_array($key, $this->permissionKeys(), true);
    }

    protected function casts(): array
    {
        return [
            'role' => AdminRole::class,
            'status' => AdminStatus::class,
            'preferred_language' => Locale::class,
            'password' => 'hashed',
            'totp_secret' => 'encrypted',
            'recovery_codes_hashes' => 'array',
            'is_2fa_enabled' => 'boolean',
            'is_2fa_enforced_at' => 'datetime',
            'recovery_codes_generated_at' => 'datetime',
            'password_changed_at' => 'datetime',
            'failed_login_count' => 'integer',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
        ];
    }
}
