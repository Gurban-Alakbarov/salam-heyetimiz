import { Bell, Building2, CreditCard, FileText, HardDrive, KeyRound, LayoutDashboard, ReceiptText, RotateCcw, Satellite, ScrollText, Settings, UsersRound, Users } from 'lucide-react'
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

// Enterprise menu order: operations first (complexes → residents → devices → billing),
// then administration (admins → access), then platform (traccar → audit → settings).
export const navItems: NavItem[] = [
  { to: '/', label: 'İdarə paneli', icon: LayoutDashboard, end: true, permission: PERM.dashboardView },
  { to: '/complexes', label: 'Komplekslər', icon: Building2, permission: PERM.complexesView },
  { to: '/residents', label: 'Sakinlər', icon: UsersRound, permission: PERM.residentsView },
  { to: '/devices', label: 'Cihazlar', icon: HardDrive, permission: PERM.devicesView },
  { to: '/subscriptions', label: 'Abunəliklər', icon: CreditCard, permission: PERM.subscriptionsView },
  { to: '/orders', label: 'Sifarişlər', icon: ReceiptText, permission: PERM.ordersView },
  { to: '/refunds', label: 'Geri qaytarmalar', icon: RotateCcw, permission: PERM.refundsView },
  { to: '/payment-logs', label: 'Ödəniş logları', icon: FileText, permission: PERM.ordersView },
  { to: '/notifications', label: 'Bildirişlər', icon: Bell, permission: PERM.notificationsView },
  { to: '/admins', label: 'Adminlər', icon: Users, permission: PERM.adminsView },
  { to: '/access', label: 'Giriş nəzarəti', icon: KeyRound, permission: PERM.accessManage },
  { to: '/traccar', label: 'Traccar', icon: Satellite, permission: PERM.systemSettings },
  { to: '/audit', label: 'Audit jurnalı', icon: ScrollText, permission: PERM.auditView },
  { to: '/settings', label: 'Parametrlər', icon: Settings, permission: PERM.systemSettings },
]
