import { Bell, Boxes, Building2, Inbox, ClipboardList, CreditCard, FileText, HardDrive, KeyRound, LayoutDashboard, LifeBuoy, ReceiptText, RotateCcw, ScrollText, Settings, Tag, UsersRound, Users } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { PERM } from '@/auth/permissions'
import type { InboxCounts } from '@/api/inboxCounts'
import type { Permission } from '@/types/api'

export interface NavItem {
  to: string
  label: string
  icon: LucideIcon
  end?: boolean
  /** The sidebar item is shown only if the admin holds this permission. */
  permission: Permission
  /** Shows a red number: how many items wait for someone. */
  count?: keyof InboxCounts
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
      { to: '/applications', label: 'Müraciətlər', icon: ClipboardList, permission: PERM.applicationsView, count: 'applications' },
      { to: '/site-leads', label: 'Sayt müraciətləri', icon: Inbox, permission: PERM.applicationsView, count: 'site_leads' },
      { to: '/complexes', label: 'Komplekslər', icon: Building2, permission: PERM.complexesView },
      { to: '/residents', label: 'Sakinlər', icon: UsersRound, permission: PERM.residentsView },
      { to: '/devices', label: 'Cihazlar', icon: HardDrive, permission: PERM.devicesView },
      { to: '/support', label: 'Qapı açılmır (dəstək)', icon: LifeBuoy, permission: PERM.residentsView },
    ],
  },
  {
    label: 'Maliyyə',
    items: [
      { to: '/subscriptions', label: 'Abunəliklər', icon: CreditCard, end: true, permission: PERM.subscriptionsView },
      { to: '/subscriptions/prices', label: 'Xüsusi qiymətlər', icon: Tag, permission: PERM.subscriptionsManage },
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
      { to: '/catalog', label: 'Kataloq (modellər)', icon: Boxes, permission: PERM.catalogManage },
      { to: '/settings', label: 'Parametrlər', icon: Settings, permission: PERM.systemSettings },
    ],
  },
]

/** Flat list (header title lookup, tests). */
export const navItems: NavItem[] = navGroups.flatMap((g) => g.items)
