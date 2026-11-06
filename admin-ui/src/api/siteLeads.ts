import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { PageMeta } from './applications'

export type SiteLeadStatus = 'new' | 'contacted' | 'closed'

export interface SiteLead {
  id: number
  name: string
  email: string
  phone: string
  place: string
  topic: string | null
  message: string | null
  locale: string
  status: SiteLeadStatus
  admin_note: string | null
  created_at: string | null
}
export interface SiteLeadParams {
  status?: string
  q?: string
  page?: number
  per_page?: number
}

export function useSiteLeads(params: SiteLeadParams) {
  return useQuery({
    queryKey: ['site-leads', params],
    queryFn: async () =>
      (await api.get<{ data: SiteLead[]; meta: PageMeta }>('/admin/v1/site-leads', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export function useUpdateSiteLead() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...body }: { id: number; status: SiteLeadStatus; admin_note?: string | null }) =>
      (await api.patch(`/admin/v1/site-leads/${id}`, body)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['site-leads'] }),
  })
}
