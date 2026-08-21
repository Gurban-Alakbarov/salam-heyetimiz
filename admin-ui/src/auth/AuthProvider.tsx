import { type ReactNode, useCallback, useEffect, useMemo, useState } from 'react'
import { api, setUnauthorizedHandler } from '@/lib/api'
import { clearToken, getToken, setToken } from '@/lib/tokenStore'
import type { AdminAuthSuccess, AdminRole, AdminUser, LoginResponse, Permission } from '@/types/api'
import { AuthContext, type AuthContextValue, type AuthStatus, type LoginResult } from './context'

interface ImpersonationResult {
  access_token: string
  admin: AdminUser
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [admin, setAdmin] = useState<AdminUser | null>(null)
  const [status, setStatus] = useState<AuthStatus>('loading')

  // Global 401 → drop the session (the guard then redirects to /login).
  useEffect(() => {
    setUnauthorizedHandler(() => {
      setAdmin(null)
      setStatus('unauthenticated')
    })
  }, [])

  // Re-hydrate the session from a stored token on boot.
  useEffect(() => {
    let active = true
    const token = getToken()
    if (!token) {
      setStatus('unauthenticated')
      return
    }
    api
      .get<AdminUser>('/admin/v1/auth/me')
      .then((res) => {
        if (!active) return
        setAdmin(res.data)
        setStatus('authenticated')
      })
      .catch(() => {
        if (!active) return
        clearToken()
        setAdmin(null)
        setStatus('unauthenticated')
      })
    return () => {
      active = false
    }
  }, [])

  const login = useCallback(async (email: string, password: string): Promise<LoginResult> => {
    const res = await api.post<LoginResponse>('/admin/v1/auth/login', { email, password })
    // Require-2FA OFF → the response is already a full session; log in directly.
    if (res.data.two_factor_required === false && res.data.access_token && res.data.admin) {
      setToken(res.data.access_token)
      setAdmin(res.data.admin)
      setStatus('authenticated')
      return { twoFactorRequired: false }
    }
    return { twoFactorRequired: true, challengeToken: res.data.challenge_token ?? '' }
  }, [])

  const verify2fa = useCallback(async (challengeToken: string, code: string, isRecovery: boolean) => {
    const body = isRecovery
      ? { challenge_token: challengeToken, recovery_code: code }
      : { challenge_token: challengeToken, totp: code }
    const res = await api.post<AdminAuthSuccess>('/admin/v1/auth/2fa/verify', body)
    setToken(res.data.access_token)
    setAdmin(res.data.admin)
    setStatus('authenticated')
  }, [])

  const logout = useCallback(async () => {
    try {
      await api.post('/admin/v1/auth/logout')
    } catch {
      /* logout is best-effort; clear locally regardless */
    }
    clearToken()
    setAdmin(null)
    setStatus('unauthenticated')
  }, [])

  const hasRole = useCallback(
    (...roles: AdminRole[]) => (admin ? roles.includes(admin.role) : false),
    [admin],
  )

  const hasPermission = useCallback(
    (...permissions: Permission[]) =>
      admin ? permissions.some((p) => admin.permissions.includes(p)) : false,
    [admin],
  )

  const impersonate = useCallback(async (adminId: number) => {
    const res = await api.post<ImpersonationResult>(`/admin/v1/admins/${adminId}/impersonate`)
    setToken(res.data.access_token)
    setAdmin(res.data.admin)
    setStatus('authenticated')
  }, [])

  const stopImpersonating = useCallback(async () => {
    const res = await api.post<ImpersonationResult>('/admin/v1/auth/stop-impersonation')
    setToken(res.data.access_token)
    setAdmin(res.data.admin)
    setStatus('authenticated')
  }, [])

  const value = useMemo<AuthContextValue>(
    () => ({
      admin,
      status,
      login,
      verify2fa,
      logout,
      hasRole,
      hasPermission,
      isImpersonating: admin?.impersonator_admin_id != null,
      impersonate,
      stopImpersonating,
    }),
    [admin, status, login, verify2fa, logout, hasRole, hasPermission, impersonate, stopImpersonating],
  )

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>
}
