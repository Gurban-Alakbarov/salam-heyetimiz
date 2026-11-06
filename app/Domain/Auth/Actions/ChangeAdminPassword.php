<?php

namespace App\Domain\Auth\Actions;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Audit\Services\AuditLogger;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * An admin changes their own password after proving the current one. password_changed_at signs out every
 * token issued earlier (JwtRequestGuard), so a stolen session ends with the old password.
 */
final class ChangeAdminPassword
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function handle(AdminUser $admin, string $current, string $new): void
    {
        if (! Hash::check($current, (string) $admin->password)) {
            throw ValidationException::withMessages(['current_password' => __('errors.admin_password_wrong')]);
        }

        $admin->forceFill(['password' => $new, 'password_changed_at' => now()])->save();

        $this->audit->record('admin.password_changed', [], AdminUser::class, (int) $admin->getKey());
    }
}
