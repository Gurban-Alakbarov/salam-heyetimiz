<?php

namespace App\Domain\Admin\Enums;

/**
 * Back-office roles (RBAC). super_admin + technical are the original two; operator, finance, support and
 * complex_manager were added for the production RBAC hierarchy. Authorization is permission-based — code
 * never branches on the role value except for the super_admin "all permissions" shortcut and the
 * complex_manager scope flag, so adding a role is enum + seeded permissions, no logic change.
 */
enum AdminRole: string
{
    case SuperAdmin = 'super_admin';
    case Technical = 'technical';
    case Operator = 'operator';
    case Finance = 'finance';
    case Support = 'support';
    case ComplexManager = 'complex_manager';

    /** super_admin implicitly holds every permission. */
    public function isSuperAdmin(): bool
    {
        return $this === self::SuperAdmin;
    }

    /** complex_manager is the only role scoped to a single complex. */
    public function isComplexScoped(): bool
    {
        return $this === self::ComplexManager;
    }

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Technical => 'Texniki',
            self::Operator => 'Operator',
            self::Finance => 'Maliyyə',
            self::Support => 'Dəstək',
            self::ComplexManager => 'Kompleks Meneceri',
        };
    }
}
