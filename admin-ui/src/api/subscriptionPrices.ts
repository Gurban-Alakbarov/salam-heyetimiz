import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type PriceScope = 'user' | 'device' | 'complex'

export interface SubscriptionPriceRow {
  id: number
  scope: PriceScope
  subject_id: number
  subject_label: string | null
  price_minor: number
  updated_at: string | null
}
export interface SetSubscriptionPriceInput {
  scope: PriceScope
  subject_id: number
  price_minor: number
}

export function useSubscriptionPrices() {
  return useQuery({
    queryKey: ['subscription-prices'],
    queryFn: async () => (await api.get<{ data: SubscriptionPriceRow[] }>('/admin/v1/subscription-prices')).data.data,
  })
}

export function useSetSubscriptionPrice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: SetSubscriptionPriceInput) => (await api.put('/admin/v1/subscription-prices', input)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscription-prices'] }),
  })
}

export function useRemoveSubscriptionPrice() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (id: number) => {
      await api.delete(`/admin/v1/subscription-prices/${id}`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['subscription-prices'] }),
  })
}
