import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { AdminRole } from '@/types/api'

export interface ComplexStats {
  devices: number
  devices_online: number
  residents: number
  managers: number
}
export interface ComplexSummary {
  id: number
  code: string
  name: string
  region_id: number | null
  address: string | null
  is_active: boolean
  stats: ComplexStats
}
export interface ComplexManager {
  id: number
  name: string
  email: string
  role: AdminRole
}
export interface ComplexDevice {
  id: number
  serial: string
  status: string
  location_label: string | null
  online: boolean
  owner: string | null
  ownership_mode?: 'private' | 'complex'
}
export interface ComplexDetail extends ComplexSummary {
  managers: ComplexManager[]
  devices: ComplexDevice[]
  // B11: OSM pin + originating legal-entity application
  latitude?: number | null
  longitude?: number | null
  legal_entity_application_id?: number | null
}
export interface ComplexMemberRow {
  user_id: number
  full_name: string | null
  email: string | null
  phone: string | null
  status: 'active' | 'removed'
  joined_at: string | null
  removed_at: string | null
  active_subscriptions: number
}
export interface InvitationRow {
  id: number
  kind: 'complex_resident' | 'family_member'
  status: 'pending' | 'accepted' | 'declined' | 'expired' | 'cancelled'
  complex_id: number | null
  device_id: number | null
  first_name: string | null
  last_name: string | null
  email: string | null
  invited_by_admin_id: number | null
  invited_by_user_id: number | null
  send_count: number
  expires_at: string | null
  accepted_at: string | null
  created_at: string | null
}
export interface ComplexInput {
  name: string
  code?: string
  region_id?: number | null
  address?: string | null
  is_active?: boolean
}

export function useComplexList() {
  return useQuery({ queryKey: ['complexes'], queryFn: async () => (await api.get<{ data: ComplexSummary[] }>('/admin/v1/complexes')).data.data })
}
export function useComplex(id: number) {
  return useQuery({
    queryKey: ['complex', id],
    queryFn: async () => (await api.get<ComplexDetail>(`/admin/v1/complexes/${id}`)).data,
    enabled: Number.isFinite(id) && id > 0,
  })
}
function useComplexInvalidation() {
  const qc = useQueryClient()
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: ['complexes'] })
    if (id) qc.invalidateQueries({ queryKey: ['complex', id] })
  }
}
export function useCreateComplex() {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (input: ComplexInput) => (await api.post<ComplexDetail>('/admin/v1/complexes', input)).data, onSuccess: () => invalidate() })
}
export function useUpdateComplex(id: number) {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (input: Partial<ComplexInput>) => (await api.patch<ComplexDetail>(`/admin/v1/complexes/${id}`, input)).data, onSuccess: () => invalidate(id) })
}
export function useDeleteComplex() {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (id: number) => { await api.delete(`/admin/v1/complexes/${id}`) }, onSuccess: () => invalidate() })
}
export function useAssignManager(complexId: number) {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (adminId: number) => (await api.post(`/admin/v1/complexes/${complexId}/managers`, { admin_id: adminId })).data, onSuccess: () => invalidate(complexId) })
}
export function useComplexMembers(complexId: number) {
  return useQuery({
    queryKey: ['complex-members', complexId],
    queryFn: async () => (await api.get<{ data: ComplexMemberRow[] }>(`/admin/v1/complexes/${complexId}/members`)).data.data,
    enabled: Number.isFinite(complexId) && complexId > 0,
  })
}
export function useComplexInvitations(complexId: number, status: string) {
  return useQuery({
    queryKey: ['complex-invitations', complexId, status],
    queryFn: async () =>
      (await api.get<{ data: InvitationRow[] }>('/admin/v1/invitations', { params: { complex_id: complexId, ...(status ? { status } : {}) } })).data.data,
    enabled: Number.isFinite(complexId) && complexId > 0,
  })
}
/** Bind (POST) / unbind (DELETE) a device to this complex — complexes.manage (B11). */
export function useComplexDeviceBinding() {
  const invalidate = useComplexInvalidation()
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ complexId, deviceId, bind }: { complexId: number; deviceId: number; bind: boolean }) => {
      if (bind) await api.post(`/admin/v1/complexes/${complexId}/devices/${deviceId}`)
      else await api.delete(`/admin/v1/complexes/${complexId}/devices/${deviceId}`)
    },
    onSuccess: (_d, v) => {
      invalidate(v.complexId)
      qc.invalidateQueries({ queryKey: ['device', v.deviceId] })
      qc.invalidateQueries({ queryKey: ['devices'] })
    },
  })
}
export function useUnassignManager(complexId: number) {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (adminId: number) => { await api.delete(`/admin/v1/complexes/${complexId}/managers/${adminId}`) }, onSuccess: () => invalidate(complexId) })
}
