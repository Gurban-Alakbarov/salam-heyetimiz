import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type {
  CreateVisitorLinkInput,
  CreateVisitorLinkResponse,
  Paginated,
  VisitorLinkAdmin,
  VisitorLinkUsage,
} from '@/types/api'

export interface VisitorLinkListParams {
  device_id?: number
  status?: string
  q?: string
  purpose?: string
  access_type?: string
  created_by?: string
  created_from?: string
  created_to?: string
  limit?: number
  cursor?: string | null
}

export function useVisitorLinks(params: VisitorLinkListParams) {
  return useQuery({
    queryKey: ['visitor-links', params],
    queryFn: async () =>
      (await api.get<Paginated<VisitorLinkAdmin>>('/admin/v1/visitor-links', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export function useVisitorLinkUsages(id: number, cursor: string | null, enabled = true) {
  return useQuery({
    queryKey: ['visitor-link-usages', id, cursor],
    queryFn: async () =>
      (
        await api.get<Paginated<VisitorLinkUsage>>(`/admin/v1/visitor-links/${id}/usages`, {
          params: cleanParams({ cursor, limit: 25 }),
        })
      ).data,
    placeholderData: keepPreviousData,
    enabled: enabled && Number.isFinite(id) && id > 0,
  })
}

export function useCreateVisitorLink(deviceId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: CreateVisitorLinkInput) =>
      (await api.post<CreateVisitorLinkResponse>(`/admin/v1/devices/${deviceId}/visitor-links`, input)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['visitor-links'] }),
  })
}

export function useRevokeVisitorLink() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) =>
      (await api.post<{ data: VisitorLinkAdmin }>(`/admin/v1/visitor-links/${id}/revoke`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['visitor-links'] }),
  })
}
