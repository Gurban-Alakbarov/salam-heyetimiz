import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface GlobalSearchResult {
  devices: { id: number; label: string; serial: string; status: string | null }[]
  people: { id: number; name: string | null; email: string | null; phone: string | null }[]
  complexes: { id: number; name: string; code: string }[]
  orders: { id: number; amount_minor: number; currency: string; status: string | null }[]
}

/** Header search: devices, people, complexes, orders (each only with its view permission; komendant-scoped). */
export function useGlobalSearch(q: string) {
  const term = q.trim()
  return useQuery({
    queryKey: ['global-search', term],
    queryFn: async () => (await api.get<GlobalSearchResult>('/admin/v1/search', { params: { q: term } })).data,
    enabled: term.length >= 2,
    placeholderData: keepPreviousData,
    staleTime: 30_000,
  })
}
