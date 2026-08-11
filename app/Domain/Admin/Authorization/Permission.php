<?php

namespace App\Domain\Admin\Authorization;

/**
 * The permission catalog — the single source of truth for every admin capability. Keys are stable machine
 * identifiers checked by the `perm:` middleware (backend) and the SPA (frontend). The catalog is seeded
 * into the `permissions` table; adding a capability is one const + one definition row + role assignments
 * (all data) — authorization logic never changes.
 *
 * "Enforced now" = an existing admin endpoint checks it. Others are defined + role-assigned + UI-gated and
 * become enforced automatically when their feature ships (apartments, vehicles, invoices, settings pages).
 */
final class Permission
{
    // dashboard
    public const DASHBOARD_VIEW = 'dashboard.view';

    // system settings
    public const SYSTEM_SETTINGS_MANAGE = 'system.settings.manage';
    public const SYSTEM_SMS_MANAGE = 'system.sms.manage';
    public const SYSTEM_PAYMENT_MANAGE = 'system.payment.manage';
    public const SYSTEM_TRACCAR_MANAGE = 'system.traccar.manage';

    // admins & RBAC
    public const ADMINS_VIEW = 'admins.view';
    public const ADMINS_CREATE = 'admins.create';
    public const ADMINS_UPDATE = 'admins.update';
    public const ADMINS_DELETE = 'admins.delete';
    public const ADMINS_ASSIGN_ROLES = 'admins.assign_roles';
    public const ADMINS_IMPERSONATE = 'admins.impersonate';

    // audit
    public const AUDIT_VIEW = 'audit.view';

    // access control (roles + permissions + per-user overrides)
    public const ACCESS_MANAGE = 'access.manage';

    // complexes
    public const COMPLEXES_VIEW = 'complexes.view';
    public const COMPLEXES_MANAGE = 'complexes.manage';

    // devices / barriers
    public const DEVICES_VIEW = 'devices.view';
    public const DEVICES_CREATE = 'devices.create';
    public const DEVICES_UPDATE = 'devices.update';
    public const DEVICES_RECONCILE = 'devices.reconcile';
    public const DEVICES_DISABLE = 'devices.disable';
    public const DEVICES_DECOMMISSION = 'devices.decommission';
    public const DEVICES_TRANSFER = 'devices.transfer';
    public const DEVICES_ASSIGN = 'devices.assign';
    public const BARRIERS_ASSIGN = 'barriers.assign';
    public const DEVICES_DIAGNOSTICS_VIEW = 'devices.diagnostics.view';
    public const DEVICES_TRACCAR_VIEW = 'devices.traccar.view';

    // whitelist
    public const WHITELIST_VIEW = 'whitelist.view';
    public const WHITELIST_MANAGE = 'whitelist.manage';

    // open commands
    public const COMMANDS_VIEW = 'commands.view';
    public const COMMANDS_TEST = 'commands.test';

    // visitor links (shareable guest access)
    public const VISITOR_LINKS_VIEW = 'visitor_links.view';
    public const VISITOR_LINKS_MANAGE = 'visitor_links.manage';

    // residents / roster
    public const RESIDENTS_VIEW = 'residents.view';
    public const RESIDENTS_CREATE = 'residents.create';
    public const RESIDENTS_UPDATE = 'residents.update';
    public const RESIDENTS_DELETE = 'residents.delete';

    // apartments
    public const APARTMENTS_VIEW = 'apartments.view';
    public const APARTMENTS_MANAGE = 'apartments.manage';

    // vehicles
    public const VEHICLES_VIEW = 'vehicles.view';
    public const VEHICLES_MANAGE = 'vehicles.manage';

    // finance
    public const PAYMENTS_VIEW = 'payments.view';
    public const ORDERS_VIEW = 'orders.view';
    public const REFUNDS_VIEW = 'refunds.view';
    public const REFUNDS_CREATE = 'refunds.create';
    public const SUBSCRIPTIONS_VIEW = 'subscriptions.view';
    public const SUBSCRIPTIONS_MANAGE = 'subscriptions.manage';
    public const INVOICES_VIEW = 'invoices.view';
    public const REPORTS_VIEW = 'reports.view';

    // support
    public const SUPPORT_OTP_RESEND = 'support.otp.resend';

    /**
     * Full catalog as [key => [group, label]]. Order is display order within the group.
     *
     * @return array<string, array{group: string, label: string}>
     */
    public static function definitions(): array
    {
        return [
            self::DASHBOARD_VIEW => ['group' => 'dashboard', 'label' => 'İdarə panelini gör'],

            self::SYSTEM_SETTINGS_MANAGE => ['group' => 'system', 'label' => 'Sistem parametrləri'],
            self::SYSTEM_SMS_MANAGE => ['group' => 'system', 'label' => 'SMS parametrləri'],
            self::SYSTEM_PAYMENT_MANAGE => ['group' => 'system', 'label' => 'Ödəniş parametrləri'],
            self::SYSTEM_TRACCAR_MANAGE => ['group' => 'system', 'label' => 'Traccar parametrləri'],

            self::ADMINS_VIEW => ['group' => 'admins', 'label' => 'Adminləri gör'],
            self::ADMINS_CREATE => ['group' => 'admins', 'label' => 'Admin yarat'],
            self::ADMINS_UPDATE => ['group' => 'admins', 'label' => 'Admin redaktə et'],
            self::ADMINS_DELETE => ['group' => 'admins', 'label' => 'Admin sil'],
            self::ADMINS_ASSIGN_ROLES => ['group' => 'admins', 'label' => 'Rol təyin et'],
            self::ADMINS_IMPERSONATE => ['group' => 'admins', 'label' => 'Admin kimi daxil ol (impersonation)'],

            self::AUDIT_VIEW => ['group' => 'audit', 'label' => 'Audit jurnalını gör'],

            self::ACCESS_MANAGE => ['group' => 'access', 'label' => 'Giriş nəzarəti (rollar/icazələr)'],

            self::COMPLEXES_VIEW => ['group' => 'complexes', 'label' => 'Kompleksləri gör'],
            self::COMPLEXES_MANAGE => ['group' => 'complexes', 'label' => 'Kompleksləri idarə et'],

            self::DEVICES_VIEW => ['group' => 'devices', 'label' => 'Cihazları gör'],
            self::DEVICES_CREATE => ['group' => 'devices', 'label' => 'Cihaz yarat'],
            self::DEVICES_UPDATE => ['group' => 'devices', 'label' => 'Cihaz redaktə et'],
            self::DEVICES_RECONCILE => ['group' => 'devices', 'label' => 'Cihaz reconcile et'],
            self::DEVICES_DISABLE => ['group' => 'devices', 'label' => 'Cihaz bağla/aç'],
            self::DEVICES_DECOMMISSION => ['group' => 'devices', 'label' => 'Cihaz çıxar'],
            self::DEVICES_TRANSFER => ['group' => 'devices', 'label' => 'Sahibliyi köçür'],
            self::DEVICES_ASSIGN => ['group' => 'devices', 'label' => 'Cihaza sahib təyin et'],
            self::BARRIERS_ASSIGN => ['group' => 'devices', 'label' => 'Barrier təyin et'],
            self::DEVICES_DIAGNOSTICS_VIEW => ['group' => 'devices', 'label' => 'Diaqnostika'],
            self::DEVICES_TRACCAR_VIEW => ['group' => 'devices', 'label' => 'Traccar statusu / firmware'],

            self::WHITELIST_VIEW => ['group' => 'whitelist', 'label' => 'Whitelist gör'],
            self::WHITELIST_MANAGE => ['group' => 'whitelist', 'label' => 'Whitelist idarə et'],

            self::COMMANDS_VIEW => ['group' => 'commands', 'label' => 'Açılış tarixçəsi'],
            self::COMMANDS_TEST => ['group' => 'commands', 'label' => 'Test açılış göndər'],

            self::VISITOR_LINKS_VIEW => ['group' => 'commands', 'label' => 'Qonaq linklərini gör'],
            self::VISITOR_LINKS_MANAGE => ['group' => 'commands', 'label' => 'Qonaq linki yarat/ləğv et'],

            self::RESIDENTS_VIEW => ['group' => 'residents', 'label' => 'Sakinləri gör'],
            self::RESIDENTS_CREATE => ['group' => 'residents', 'label' => 'Sakin yarat'],
            self::RESIDENTS_UPDATE => ['group' => 'residents', 'label' => 'Sakin redaktə et'],
            self::RESIDENTS_DELETE => ['group' => 'residents', 'label' => 'Sakin sil'],

            self::APARTMENTS_VIEW => ['group' => 'apartments', 'label' => 'Mənzilləri gör'],
            self::APARTMENTS_MANAGE => ['group' => 'apartments', 'label' => 'Mənzilləri idarə et'],

            self::VEHICLES_VIEW => ['group' => 'vehicles', 'label' => 'Avtomobilləri gör'],
            self::VEHICLES_MANAGE => ['group' => 'vehicles', 'label' => 'Avtomobilləri idarə et'],

            self::PAYMENTS_VIEW => ['group' => 'finance', 'label' => 'Ödənişləri gör'],
            self::ORDERS_VIEW => ['group' => 'finance', 'label' => 'Sifarişləri gör'],
            self::REFUNDS_VIEW => ['group' => 'finance', 'label' => 'Geri qaytarmaları gör'],
            self::REFUNDS_CREATE => ['group' => 'finance', 'label' => 'Geri qaytarma et'],
            self::SUBSCRIPTIONS_VIEW => ['group' => 'finance', 'label' => 'Abunəlikləri gör'],
            self::SUBSCRIPTIONS_MANAGE => ['group' => 'finance', 'label' => 'Abunəlikləri idarə et'],
            self::INVOICES_VIEW => ['group' => 'finance', 'label' => 'Qaimələri gör'],
            self::REPORTS_VIEW => ['group' => 'finance', 'label' => 'Hesabatları gör'],

            self::SUPPORT_OTP_RESEND => ['group' => 'support', 'label' => 'OTP yenidən göndər'],
        ];
    }

    /** @return array<int, string> every permission key */
    public static function all(): array
    {
        return array_keys(self::definitions());
    }
}
