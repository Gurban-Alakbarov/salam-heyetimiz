import axios, { AxiosError, type AxiosInstance } from 'axios'
import { clearToken, getToken } from './tokenStore'

export interface ApiErrorEnvelope {
  error: {
    code: string
    message_key?: string
    message: string
    details?: Record<string, unknown> | null
    fields?: Record<string, string[]>
    request_id?: string
  }
}

/** Normalised API error from the backend `{ error: {...} }` envelope. */
export class ApiError extends Error {
  readonly code: string
  readonly status: number
  readonly fields?: Record<string, string[]>
  readonly requestId?: string

  constructor(status: number, env?: ApiErrorEnvelope, fallback = 'Xəta baş verdi.') {
    super(env?.error?.message || fallback)
    this.name = 'ApiError'
    this.status = status
    this.code = env?.error?.code ?? 'unknown'
    this.fields = env?.error?.fields
    this.requestId = env?.error?.request_id
  }

  /** First validation message for a field, if any. */
  fieldError(name: string): string | undefined {
    return this.fields?.[name]?.[0]
  }
}

export const api: AxiosInstance = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '',
  headers: { Accept: 'application/json', 'Accept-Language': 'az' },
  timeout: 20000,
})

api.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

let onUnauthorized: (() => void) | null = null
export function setUnauthorizedHandler(fn: () => void): void {
  onUnauthorized = fn
}

api.interceptors.response.use(
  (res) => res,
  (error: AxiosError<ApiErrorEnvelope>) => {
    const status = error.response?.status ?? 0
    if (status === 401) {
      clearToken()
      onUnauthorized?.()
    }
    return Promise.reject(new ApiError(status, error.response?.data, networkMessage(status)))
  },
)

function networkMessage(status: number): string {
  if (status === 0) return 'Şəbəkə xətası. İnternet bağlantınızı yoxlayın.'
  if (status >= 500) return 'Server xətası. Bir az sonra yenidən cəhd edin.'
  return 'Xəta baş verdi.'
}
