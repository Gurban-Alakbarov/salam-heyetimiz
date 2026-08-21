import type { ReactNode } from 'react'
import { useAuth } from '@/auth/useAuth'
import type { Permission } from '@/types/api'

/** Renders children only when the current admin holds at least one of the given permissions. */
export function PermissionGate({ anyOf, children }: { anyOf: Permission[]; children: ReactNode }) {
  const { hasPermission } = useAuth()
  return hasPermission(...anyOf) ? <>{children}</> : null
}
