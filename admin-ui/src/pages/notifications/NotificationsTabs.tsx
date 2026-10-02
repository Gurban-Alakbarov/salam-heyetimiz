import { NavLink } from 'react-router-dom'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { cn } from '@/lib/utils'

/** Bildirişlər → Kampaniyalar | Şablonlar (IMPLEMENTATION_PLAN §17). Each tab shows only with its permission. */
export function NotificationsTabs() {
  const { hasPermission } = useAuth()
  const tabs = [
    { to: '/notifications', label: 'Kampaniyalar', end: true, show: hasPermission(PERM.notificationsView) },
    { to: '/notifications/templates', label: 'Şablonlar', end: false, show: hasPermission(PERM.notificationTemplatesView) },
  ].filter((t) => t.show)

  if (tabs.length < 2) return null

  return (
    <nav className="inline-flex rounded-md bg-muted p-1 text-sm" aria-label="Bildiriş bölmələri">
      {tabs.map((t) => (
        <NavLink
          key={t.to}
          to={t.to}
          end={t.end}
          className={({ isActive }) => cn('rounded px-3 py-1.5 font-medium transition-colors', isActive ? 'bg-card shadow-sm' : 'text-muted-foreground hover:text-foreground')}
        >
          {t.label}
        </NavLink>
      ))}
    </nav>
  )
}
