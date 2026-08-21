import type { ReactNode } from 'react'
import { useAuth } from '@/auth/useAuth'
import type { AdminRole } from '@/types/api'

/** Renders children only when the current admin has one of the given roles. */
export function RoleGate({ roles, children }: { roles: AdminRole[]; children: ReactNode }) {
  const { hasRole } = useAuth()
  return hasRole(...roles) ? <>{children}</> : null
}
