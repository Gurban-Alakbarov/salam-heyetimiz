<?php

namespace App\Domain\Admin\Authorization;

use App\Domain\Admin\Enums\AdminRole;

/**
 * The role → permission assignment (the RBAC matrix). super_admin is implicitly all permissions and is
 * never stored. This is the seed source for `role_permissions`; it is also the documented contract in
 * docs/RBAC_PERMISSION_MATRIX.md. Editing a role's powers = editing this map + reseeding (data only).
 */
final class RolePermissionMatrix
{
    /**
     * Permission keys granted to each non-super role.
     *
     * @return array<string, array<int, string>>
     */
    public static function map(): array
    {
        return [
            AdminRole::Technical->value => [
                Permission::DASHBOARD_VIEW,
                Permission::DEVICES_VIEW,
                Permission::DEVICES_CREATE,
                Permission::DEVICES_UPDATE,
                Permission::DEVICES_RECONCILE,
                Permission::DEVICES_DIAGNOSTICS_VIEW,
                Permission::DEVICES_TRACCAR_VIEW,
                Permission::WHITELIST_VIEW,
                Permission::WHITELIST_MANAGE,
                Permission::COMMANDS_VIEW,
                Permission::COMMANDS_TEST,
                Permission::VISITOR_LINKS_VIEW,
            ],

            AdminRole::Operator->value => [
                Permission::DASHBOARD_VIEW,
                Permission::DEVICES_VIEW,
                Permission::RESIDENTS_VIEW,
                Permission::RESIDENTS_CREATE,
                Permission::RESIDENTS_UPDATE,
                Permission::RESIDENTS_DELETE,
                Permission::APARTMENTS_VIEW,
                Permission::APARTMENTS_MANAGE,
                Permission::VEHICLES_VIEW,
                Permission::VEHICLES_MANAGE,
                Permission::DEVICES_ASSIGN,
                Permission::BARRIERS_ASSIGN,
                Permission::WHITELIST_VIEW,
                Permission::WHITELIST_MANAGE,
                Permission::COMMANDS_VIEW,
                Permission::VISITOR_LINKS_VIEW,
                Permission::VISITOR_LINKS_MANAGE,
                Permission::NOTIFICATIONS_VIEW,
                Permission::NOTIFICATIONS_SEND,
            ],

            AdminRole::Finance->value => [
                Permission::DASHBOARD_VIEW,
                Permission::PAYMENTS_VIEW,
                Permission::ORDERS_VIEW,
                Permission::REFUNDS_VIEW,
                Permission::REFUNDS_CREATE,
                Permission::SUBSCRIPTIONS_VIEW,
                Permission::SUBSCRIPTIONS_MANAGE,
                Permission::INVOICES_VIEW,
                Permission::REPORTS_VIEW,
            ],

            AdminRole::Support->value => [
                Permission::DASHBOARD_VIEW,
                Permission::RESIDENTS_VIEW,
                Permission::ORDERS_VIEW,
                Permission::SUBSCRIPTIONS_VIEW,
                Permission::DEVICES_VIEW,
                Permission::COMMANDS_VIEW,
                Permission::SUPPORT_OTP_RESEND,
                Permission::VISITOR_LINKS_VIEW,
                Permission::NOTIFICATIONS_VIEW,
            ],

            AdminRole::ComplexManager->value => [
                Permission::DASHBOARD_VIEW,
                Permission::COMPLEXES_VIEW,
                Permission::RESIDENTS_VIEW,
                Permission::RESIDENTS_CREATE,
                Permission::RESIDENTS_UPDATE,
                Permission::RESIDENTS_DELETE,
                Permission::APARTMENTS_VIEW,
                Permission::APARTMENTS_MANAGE,
                Permission::BARRIERS_ASSIGN,
                Permission::DEVICES_VIEW,
                Permission::WHITELIST_VIEW,
                Permission::WHITELIST_MANAGE,
                Permission::REPORTS_VIEW,
                Permission::VISITOR_LINKS_VIEW,
                Permission::VISITOR_LINKS_MANAGE,
                Permission::NOTIFICATIONS_VIEW,
                Permission::NOTIFICATIONS_SEND,
            ],
        ];
    }

    /**
     * Resolve the permission keys for a role (super_admin = the full catalog).
     *
     * @return array<int, string>
     */
    public static function forRole(AdminRole $role): array
    {
        if ($role->isSuperAdmin()) {
            return Permission::all();
        }

        return self::map()[$role->value] ?? [];
    }
}
