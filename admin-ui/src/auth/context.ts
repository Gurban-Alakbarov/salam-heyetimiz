import { createContext } from 'react'
import type { AdminRole, AdminUser, Permission } from '@/types/api'

export type AuthStatus = 'loading' | 'authenticated' | 'unauthenticated'

/** Result of step-1 login: either a 2FA challenge is needed, or the session is already established. */
export type LoginResult =
  | { twoFactorRequired: true; challengeToken: string }
  | { twoFactorRequired: false }

export interface AuthContextValue {
  admin: AdminUser | null
  status: AuthStatus
  login: (email: string, password: string) => Promise<LoginResult>
  verify2fa: (challengeToken: string, code: string, isRecovery: boolean) => Promise<void>
  logout: () => Promise<void>
  hasRole: (...roles: AdminRole[]) => boolean
  /** True if the current admin holds at least one of the given permissions. */
  hasPermission: (...permissions: Permission[]) => boolean
  /** Whether this session is a super-admin impersonating another admin. */
  isImpersonating: boolean
  /** Begin impersonating an admin (super_admin only). */
  impersonate: (adminId: number) => Promise<void>
  /** Return to the original super-admin session. */
  stopImpersonating: () => Promise<void>
}

export const AuthContext = createContext<AuthContextValue | null>(null)
