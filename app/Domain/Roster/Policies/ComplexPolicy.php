<?php

namespace App\Domain\Roster\Policies;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Admin\Models\Complex;
use App\Domain\Roster\Services\ComplexMembershipService;
use App\Domain\Users\Models\User;

/**
 * Who may see a residential complex (IMPLEMENTATION_PLAN B4 / S-1). A mobile user: only an active resident of
 * that complex (family members are not residents). An admin: any complex, except a complex_manager, who is
 * locked to its own. Komendant access from the mobile app (linked admin) arrives in B5.
 */
class ComplexPolicy
{
    public function view(mixed $actor, Complex $complex): bool
    {
        if ($actor instanceof AdminUser) {
            $scope = $actor->complexScopeId();

            return $scope === null || $scope === (int) $complex->getKey();
        }

        return $actor instanceof User
            && app(ComplexMembershipService::class)->isMember((int) $complex->getKey(), (int) $actor->getKey());
    }
}
