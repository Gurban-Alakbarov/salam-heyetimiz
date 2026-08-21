// Admin access-token storage. sessionStorage: survives reload, cleared on tab close.
// The backend exposes no admin refresh token and no httpOnly-cookie session (token is a 30-min
// Bearer JWT in the response body), so sessionStorage is the practical store for an SPA session.
const TOKEN_KEY = 'salam_admin_token'

export function getToken(): string | null {
  try {
    return sessionStorage.getItem(TOKEN_KEY)
  } catch {
    return null
  }
}

export function setToken(token: string): void {
  try {
    sessionStorage.setItem(TOKEN_KEY, token)
  } catch {
    /* storage unavailable — session stays in memory only */
  }
}

export function clearToken(): void {
  try {
    sessionStorage.removeItem(TOKEN_KEY)
  } catch {
    /* ignore */
  }
}
