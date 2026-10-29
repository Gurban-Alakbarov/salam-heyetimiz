import { Bell, Building2, ClipboardList, CreditCard, FileText, HardDrive, KeyRound, LayoutDashboard, ReceiptText, RotateCcw, ScrollText, Settings, UsersRound, Users } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { PERM } from '@/auth/permissions'
import type { Permission } from '@/types/api'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  end?: boolean
  /** The sidebar item is shown only if the admin holds this permission. */
  permission: Permission
}

export interface NavGroup {
  /** Section heading; null = no heading (the dashboard on top). */
  label: string | null
  items: NavItem[]
}

// Menu in sections, in the order people work: daily operations, money, messages, then system administration.
// "İstifadəçilər" lives as a tab inside Sakinlər and Traccar inside Parametrlər (same pages, one way in each).
export const navGroups: NavGroup[] = [
  {
    label: null,
    items: [{ to: '/', label: 'İdarə paneli', icon: LayoutDashboard, end: true, permission: PERM.dashboardView }],
  },
  {
    label: 'Əməliyyat',
    items: [
      { to: '/applications', label: 'Müraciətlər', icon: ClipboardList, permission: PERM.applicationsView },
      { to: '/complexes', label: 'Komplekslər', icon: Building2, permission: PERM.complexesView },
      { to: '/residents', label: 'Sakinlər', icon: UsersRound, permission: PERM.residentsView },
      { to: '/devices', label: 'Cihazlar', icon: HardDrive, permission: PERM.devicesView },
    ],
  },
  {
    label: 'Maliyyə',
    items: [
      { to: '/subscriptions', label: 'Abunəliklər', icon: CreditCard, permission: PERM.subscriptionsView },
      { to: '/orders', label: 'Sifarişlər', icon: ReceiptText, permission: PERM.ordersView },
      { to: '/refunds', label: 'Geri qaytarmalar', icon: RotateCcw, permission: PERM.refundsView },
      { to: '/payment-logs', label: 'Bank sorğuları (texniki)', icon: FileText, permission: PERM.ordersView },
    ],
  },
  {
    label: 'Bildirişlər',
    items: [{ to: '/notifications', label: 'Bildirişlər', icon: Bell, permission: PERM.notificationsView }],
  },
  {
    label: 'Sistem',
    items: [
      { to: '/admins', label: 'Adminlər', icon: Users, permission: PERM.adminsView },
      { to: '/access', label: 'Admin icazələri', icon: KeyRound, permission: PERM.accessManage },
      { to: '/audit', label: 'Audit jurnalı', icon: ScrollText, permission: PERM.auditView },
      { to: '/settings', label: 'Parametrlər', icon: Settings, permission: PERM.systemSettings },
    ],
  },
]

/** Flat list (header title lookup, tests). */
export const navItems: NavItem[] = navGroups.flatMap((g) => g.items)
