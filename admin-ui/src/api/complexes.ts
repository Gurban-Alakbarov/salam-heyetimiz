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
}
export interface ComplexDetail extends ComplexSummary {
  managers: ComplexManager[]
  devices: ComplexDevice[]
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
export function useUnassignManager(complexId: number) {
  const invalidate = useComplexInvalidation()
  return useMutation({ mutationFn: async (adminId: number) => { await api.delete(`/admin/v1/complexes/${complexId}/managers/${adminId}`) }, onSuccess: () => invalidate(complexId) })
}
