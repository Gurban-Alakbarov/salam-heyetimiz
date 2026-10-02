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

export interface SubscriptionPeriodRow {
  id: number
  kind: string
  period_start: string | null
  period_end: string | null
  amount_minor: number
  order_id: number | null
  paid_by_user_id: number | null
}
export interface SubscriptionDetail extends Subscription {
  cancelled_at: string | null
  cancellation_reason: string | null
  is_legacy_comp: boolean
  device: { id: number; serial: string; location_label: string | null; ownership_mode: string; complex_id: number | null } | null
  beneficiary: { id: number; full_name: string | null; phone: string | null } | null
  family_link_id: number | null
  periods: SubscriptionPeriodRow[]
}

export function useSubscription(id: number) {
  return useQuery({
    queryKey: ['subscription', id],
    queryFn: async () => (await api.get<{ data: SubscriptionDetail }>(`/admin/v1/subscriptions/${id}`)).data.data,
    enabled: Number.isFinite(id) && id > 0,
  })
}

export function useSubscriptions(params: SubscriptionListParams) {
  return useQuery({
    queryKey: ['subscriptions', params],
    queryFn: async () =>
      (await api.get<Paginated<Subscription>>('/admin/v1/subscriptions', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}
