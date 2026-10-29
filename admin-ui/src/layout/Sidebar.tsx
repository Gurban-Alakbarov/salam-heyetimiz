import { NavLink } from 'react-router-dom'
import { ShieldCheck } from 'lucide-react'
import { useAuth } from '@/auth/useAuth'
import { cn } from '@/lib/utils'
import { navGroups } from './navItems'

export function SidebarBrand() {
  return (
    <div className="flex h-16 items-center gap-2 border-b px-6">
      <div className="flex h-8 w-8 items-center justify-center rounded-md bg-primary text-primary-foreground">
        <ShieldCheck className="h-5 w-5" />
      </div>
      <div className="leading-tight">
        <p className="text-sm font-semibold">Salam</p>
        <p className="text-xs text-muted-foreground">Admin Panel</p>
      </div>
    </div>
  )
}

export function Sidebar({ onNavigate }: { onNavigate?: () => void }) {
  const { hasPermission } = useAuth()
  const groups = navGroups
    .map((g) => ({ ...g, items: g.items.filter((item) => hasPermission(item.permission)) }))
    .filter((g) => g.items.length > 0)

  return (
    <nav className="flex flex-1 flex-col gap-1 overflow-y-auto p-3">
      {groups.map((group) => (
        <div key={group.label ?? 'top'} className="flex flex-col gap-1">
          {group.label && (
            <p className="mt-3 px-3 pb-1 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground/70">
              {group.label}
            </p>
          )}
          {group.items.map((item) => {
            const Icon = item.icon
            return (
              <NavLink
                key={item.to}
                to={item.to}
                end={item.end}
                onClick={onNavigate}
                className={({ isActive }) =>
                  cn(
                    'flex items-center gap-3 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-primary/10 text-primary'
                      : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                  )
                }
              >
                <Icon className="h-4 w-4" />
                {item.label}
              </NavLink>
            )
          })}
        </div>
      ))}
    </nav>
  )
}
