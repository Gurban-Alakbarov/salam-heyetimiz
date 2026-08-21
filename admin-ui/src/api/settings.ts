import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type SettingType = 'string' | 'int' | 'bool' | 'secret' | 'select' | 'text'
export interface SettingField {
  key: string
  type: SettingType
  label: string
  options?: string[] | null
}
export interface SettingGroup {
  group: string
  fields: SettingField[]
  values: Record<string, unknown>
  secrets_set: Record<string, boolean>
}
export interface SettingsResponse {
  groups: SettingGroup[]
  readonly: Record<string, Record<string, string>>
}
export interface TestResult {
  ok: boolean
  message: string
  meta?: Record<string, unknown>
}
export interface SystemHealth {
  app: { laravel: string; php: string; env: string }
  database: { ok: boolean }
  redis: { ok: boolean }
  queue: { connection: string; failed: number }
  horizon: { installed: boolean }
  disk: { total_gb: number; free_gb: number; used_percent: number }
  memory: { php_used_mb: number; php_limit: string }
  cpu: { load_1m: number | null }
  smtp: { configured: boolean }
  payment: { enabled: boolean; configured: boolean }
  cloudflare: { proxied: boolean; ray: string | null }
  traccar: { status: string; version: string | null; device_count: number; queue_size: number; last_webhook: string | null }
}

export function useSettings() {
  return useQuery({ queryKey: ['settings'], queryFn: async () => (await api.get<SettingsResponse>('/admin/v1/settings')).data })
}
export function useUpdateGroup() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ group, values }: { group: string; values: Record<string, unknown> }) =>
      (await api.patch(`/admin/v1/settings/${group}`, values)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['settings'] }),
  })
}
export function useTestSetting(group: string) {
  return useMutation({ mutationFn: async () => (await api.post<TestResult>(`/admin/v1/settings/${group}/test`)).data })
}
export function useSystemHealth() {
  return useQuery({ queryKey: ['system-health'], queryFn: async () => (await api.get<SystemHealth>('/admin/v1/system/health')).data, refetchInterval: 30000 })
}

// ---- v2: ops, import/export, version history ----

export interface SettingsVersion {
  id: number
  reason: 'update' | 'import' | 'restore'
  scope_group: string | null
  changes: Array<{ key: string; old?: string; new?: string }> | null
  actor_label: string | null
  ip: string | null
  created_at: string | null
}
export interface VersionDiff {
  from: SettingsVersion
  to: SettingsVersion
  diff: Array<{ key: string; from: unknown; to: unknown }>
}
export interface TraccarStatus {
  connection: string
  version: string | null
  device_count: number
  queue_size: number
  last_webhook: string | null
  last_command: string | null
  last_ack: string | null
  last_device_sync: string | null
}

export function useTestEmail() {
  return useMutation({ mutationFn: async (to: string) => (await api.post<TestResult>('/admin/v1/settings/email/send-test', { to })).data })
}
export function useTestEmailOtp() {
  return useMutation({
    mutationFn: async ({ to, template }: { to: string; template: string }) =>
      (await api.post<TestResult>('/admin/v1/settings/email/send-test-otp', { to, template })).data,
  })
}
export function useTestSms() {
  return useMutation({ mutationFn: async (to: string) => (await api.post<TestResult>('/admin/v1/settings/sms/send-test', { to })).data })
}
export function useForceLogout() {
  return useMutation({ mutationFn: async () => (await api.post('/admin/v1/settings/security/force-logout')).data })
}
export function useTraccarStatus(enabled: boolean) {
  return useQuery({
    queryKey: ['traccar-status'],
    queryFn: async () => (await api.get<TraccarStatus>('/admin/v1/settings/traccar/status')).data,
    enabled,
    refetchInterval: 30000,
  })
}
export function useSettingsVersions(enabled: boolean) {
  return useQuery({
    queryKey: ['settings-versions'],
    queryFn: async () => (await api.get<{ data: SettingsVersion[] }>('/admin/v1/settings/versions')).data.data,
    enabled,
  })
}
export function useCompareVersions() {
  return useMutation({
    mutationFn: async ({ from, to }: { from: number; to: number }) =>
      (await api.get<VersionDiff>(`/admin/v1/settings/versions/compare?from=${from}&to=${to}`)).data,
  })
}
export function useRestoreVersion() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => (await api.post(`/admin/v1/settings/versions/${id}/restore`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['settings'] })
      qc.invalidateQueries({ queryKey: ['settings-versions'] })
    },
  })
}
export function useImportSettings() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (settings: Record<string, unknown>) => (await api.post('/admin/v1/settings/import', { settings })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['settings'] })
      qc.invalidateQueries({ queryKey: ['settings-versions'] })
    },
  })
}
export async function exportSettings(): Promise<Record<string, unknown>> {
  return (await api.get('/admin/v1/settings/export')).data
}

// ---- payments (Kapital / BirPay) diagnostics ----
export interface PaymentsDiagnostics {
  ok: boolean
  message: string
  oauth: { ok: boolean; message: string; expires_in: number | null; token_type: string | null; scope: string | null }
  authenticated: { ok: boolean; http_status: number | null; message: string }
  host: string
  mode: string
  merchant_id: string
  terminal_id: string
}
export interface CreatePaymentTestResult {
  ok: boolean
  payment_id?: string
  status?: string
  confirm_url?: string | null
  has_confirm_url?: boolean
  code?: string
  message?: string
}
export function usePaymentsDiagnostics() {
  return useMutation({ mutationFn: async () => (await api.post<PaymentsDiagnostics>('/admin/v1/settings/payments/test')).data })
}
export function usePaymentsTestCreate() {
  return useMutation({ mutationFn: async () => (await api.post<CreatePaymentTestResult>('/admin/v1/settings/payments/test-create')).data })
}
