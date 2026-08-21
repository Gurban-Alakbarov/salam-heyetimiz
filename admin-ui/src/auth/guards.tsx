import type { ReactNode } from 'react'
import { Navigate, Outlet, useLocation } from 'react-router-dom'
import { LoadingState } from '@/components/states'
import { ForbiddenPage } from '@/pages/ForbiddenPage'
import type { Permission } from '@/types/api'
import { useAuth } from './useAuth'

function FullScreenLoader() {
  return (
    <div className="flex min-h-screen items-center justify-center">
      <LoadingState />
    </div>
  )
}

export function ProtectedRoute() {
  const { status } = useAuth()
  const location = useLocation()
  if (status === 'loading') return <FullScreenLoader />
  if (status === 'unauthenticated') return <Navigate to="/login" replace state={{ from: location }} />
  return <Outlet />
}

export function PublicRoute() {
  const { status } = useAuth()
  if (status === 'loading') return <FullScreenLoader />
  if (status === 'authenticated') return <Navigate to="/" replace />
  return <Outlet />
}

/** Route-level permission guard: renders the page only if the admin holds one of `anyOf`, else a 403 page. */
export function RequirePermission({ anyOf, children }: { anyOf: Permission[]; children: ReactNode }) {
  const { hasPermission } = useAuth()
  return hasPermission(...anyOf) ? <>{children}</> : <ForbiddenPage />
}
