<?php

namespace App\Domain\Admin\Services;

use App\Domain\Admin\Enums\AdminRole;
use App\Domain\Admin\Enums\AdminStatus;
use App\Domain\Admin\Models\AdminUser;
use App\Domain\Users\Models\User;

/**
 * Resolves the Komendant authority of a MOBILE user (IMPLEMENTATION_PLAN B5 / BR-15). The authority lives on
 * the linked back-office account: admin_users.user_id = the user, role = complex_manager, status = active,
 * complex assigned. No separate auth — the user signs in with the normal email-OTP flow. Suspending or
 * unlinking the admin, or moving it to another complex, takes effect on the very next request.
 */
final class KomendantContext
{
    public function managerFor(User $user): ?AdminUser
    {
        /** @var AdminUser|null $admin */
        $admin = AdminUser::query()
            ->with('complex')
            ->where('user_id', $user->getKey())
            ->where('role', AdminRole::ComplexManager->value)
            ->where('status', AdminStatus::Active->value)
            ->whereNotNull('complex_id')
            ->first();

        return $admin !== null && $admin->complex !== null ? $admin : null;
    }

    public function complexIdFor(User $user): ?int
    {
        return $this->managerFor($user)?->complexScopeId();
    }
}
