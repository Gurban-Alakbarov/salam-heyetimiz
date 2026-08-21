import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { AdminRole, AdminUser, Paginated } from '@/types/api'

export interface CreateAdminInput {
  email: string
  name: string
  password: string
  role: AdminRole
  complex_id?: number | null
}

export interface CreatedAdmin {
  admin: AdminUser
  totp_secret: string
  recovery_codes: string[]
}

export interface Complex {
  id: number
  code: string
  name: string
  region_id: number | null
}

export interface AuditEntry {
  id: number
  actor_kind: string
  actor_id: number | null
  actor_label: string | null
  impersonator_admin_id: number | null
  action: string
  auditable_type: string | null
  auditable_id: number | null
  payload: Record<string, unknown> | null
  ip: string | null
  user_agent?: string | null
  created_at: string | null
}

export function useAdmins() {
  return useQuery({
    queryKey: ['admins'],
    queryFn: async () => (await api.get<{ data: AdminUser[] }>('/admin/v1/admins')).data.data,
  })
}

export function useComplexes() {
  return useQuery({
    queryKey: ['complexes'],
    queryFn: async () => (await api.get<{ data: Complex[] }>('/admin/v1/complexes')).data.data,
  })
}

export function useCreateAdmin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: CreateAdminInput) =>
      (await api.post<CreatedAdmin>('/admin/v1/admins', input)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admins'] }),
  })
}

export function useDeactivateAdmin() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/v1/admins/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['admins'] }),
  })
}

export function useAudit(cursor: string | null) {
  return useQuery({
    queryKey: ['audit', cursor],
    queryFn: async () =>
      (await api.get<Paginated<AuditEntry>>('/admin/v1/audit', { params: cleanParams({ cursor, limit: 30 }) })).data,
  })
}
