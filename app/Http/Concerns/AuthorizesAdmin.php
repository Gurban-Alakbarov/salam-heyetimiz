<?php

namespace App\Http\Concerns;

use App\Domain\Admin\Models\AdminUser;
use App\Domain\Devices\Models\Device;
use Illuminate\Http\Request;

/**
 * Permission-based admin authorization, used at the controller layer (the project's existing authz seam —
 * we replaced the old role-equality `ensure*` helpers with permission checks). Every admin action calls
 * requirePermission(...) with a Permission::* key; complex_manager admins are additionally scoped to their
 * own complex via the scope helpers.
 */
trait AuthorizesAdmin
{
    /** Require the caller to be an admin holding $permission. Returns the admin for downstream scoping. */
    protected function requirePermission(Request $request, string $permission): AdminUser
    {
        $actor = $request->user();
        abort_unless($actor instanceof AdminUser, 401);
        abort_unless($actor->hasPermission($permission), 403);

        return $actor;
    }

    /** Require the caller to be an admin holding at least one of $permissions. */
    protected function requireAnyPermission(Request $request, string ...$permissions): AdminUser
    {
        $actor = $request->user();
        abort_unless($actor instanceof AdminUser, 401);

        foreach ($permissions as $permission) {
            if ($actor->hasPermission($permission)) {
                return $actor;
            }
        }

        abort(403);
    }

    /** The complex id the caller is locked to (complex_manager), or null for global roles. */
    protected function complexScopeId(Request $request): ?int
    {
        $actor = $request->user();

        return $actor instanceof AdminUser ? $actor->complexScopeId() : null;
    }

    /**
     * Enforce complex scope on a single device: a complex_manager may only touch devices in its own
     * complex. Hidden as 404 (not 403) so scoped admins cannot probe other complexes' device ids.
     */
    protected function assertDeviceInScope(Request $request, Device $device): void
    {
        $scope = $this->complexScopeId($request);
        if ($scope !== null && (int) ($device->complex_id ?? 0) !== $scope) {
            abort(404);
        }
    }
}
