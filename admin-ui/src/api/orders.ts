import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { Order, Paginated } from '@/types/api'

export interface OrderListParams {
  status?: string
  q?: string
  limit?: number
  cursor?: string | null
}

export function useOrders(params: OrderListParams) {
  return useQuery({
    queryKey: ['orders', params],
    queryFn: async () =>
      (await api.get<Paginated<Order>>('/admin/v1/orders', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export function useOrder(id: number) {
  return useQuery({
    queryKey: ['order', id],
    queryFn: async () => (await api.get<Order>(`/admin/v1/orders/${id}`)).data,
    enabled: Number.isFinite(id) && id > 0,
  })
}

export function useRecheckOrder(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post<Order>(`/admin/v1/orders/${id}/recheck`)).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['order', id] })
      qc.invalidateQueries({ queryKey: ['orders'] })
    },
  })
}

export interface RefundOrderInput {
  amount_minor: number
  reason: string
}

export function useRefundOrder(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: RefundOrderInput) => {
      await api.post(`/admin/v1/orders/${id}/refund`, input)
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['order', id] })
      qc.invalidateQueries({ queryKey: ['orders'] })
      qc.invalidateQueries({ queryKey: ['refunds'] })
    },
  })
}
