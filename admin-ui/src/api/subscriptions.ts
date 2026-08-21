import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { Paginated, Subscription } from '@/types/api'

export interface SubscriptionListParams {
  status?: string
  tier?: string
  limit?: number
  cursor?: string | null
}

export function useSubscriptions(params: SubscriptionListParams) {
  return useQuery({
    queryKey: ['subscriptions', params],
    queryFn: async () =>
      (await api.get<Paginated<Subscription>>('/admin/v1/subscriptions', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}
