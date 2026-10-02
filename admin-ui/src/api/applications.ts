import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'

// Registration applications (B9 backend). Physical and legal are separate models / endpoints / tabs.
export type IndividualStatus = 'new' | 'contacted' | 'in_progress' | 'installed' | 'rejected'
export type LegalStatus = 'pending' | 'approved' | 'rejected'

export interface GeoPoint {
  latitude: number
  longitude: number
}
export interface IndividualApplication {
  id: number
  type: 'individual'
  status: IndividualStatus
  full_name: string
  phone: string
  email: string
  address: string
  location: GeoPoint
  region_id: number | null
  note: string | null
  rejection_reason: string | null
  created_at: string | null
  status_changed_at: string | null
  user_id: number
  admin_note: string | null
  handled_by_admin_id: number | null
  device_id: number | null
  next_statuses: IndividualStatus[]
}
export interface LegalApplication {
  id: number
  type: 'legal'
  status: LegalStatus
  complex_name: string
  legal_name: string
  voen: string
  legal_address: string
  contact_person_name: string
  contact_phone: string
  contact_email: string
  address: string
  location: GeoPoint
  region_id: number | null
  apartments_count: number | null
  note: string | null
  rejection_reason: string | null
  complex_id: number | null
  created_at: string | null
  reviewed_at: string | null
  applicant_user_id: number
  reviewed_by_admin_id: number | null
  complex?: { id: number; code: string; name: string }
}
export interface PageMeta {
  total: number
  page: number
  per_page: number
}
export interface ListParams {
  status?: string
  q?: string
  page?: number
  per_page?: number
}

export function useIndividualApplications(params: ListParams) {
  return useQuery({
    queryKey: ['applications', 'individual', params],
    queryFn: async () =>
      (await api.get<{ data: IndividualApplication[]; meta: PageMeta }>('/admin/v1/applications/individual', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export function useLegalApplications(params: ListParams) {
  return useQuery({
    queryKey: ['applications', 'legal', params],
    queryFn: async () =>
      (await api.get<{ data: LegalApplication[]; meta: PageMeta }>('/admin/v1/applications/legal', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export interface IndividualStatusInput {
  status: IndividualStatus
  admin_note?: string | null
  rejection_reason?: string | null
  device_id?: number | null
}

export function useUpdateIndividualApplication(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: IndividualStatusInput) =>
      (await api.patch<{ data: IndividualApplication }>(`/admin/v1/applications/individual/${id}`, input)).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['applications', 'individual'] }),
  })
}

export function useApproveLegalApplication(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post<{ data: LegalApplication }>(`/admin/v1/applications/legal/${id}/approve`, {})).data.data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['applications', 'legal'] })
      qc.invalidateQueries({ queryKey: ['complexes'] })
    },
  })
}

export function useRejectLegalApplication(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (reason: string) => (await api.post<{ data: LegalApplication }>(`/admin/v1/applications/legal/${id}/reject`, { reason })).data.data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['applications', 'legal'] }),
  })
}
