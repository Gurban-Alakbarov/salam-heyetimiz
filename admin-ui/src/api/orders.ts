import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { Order, Paginated } from '@/types/api'

export interface OrderListParams {
  status?: string
  q?: string
  /** K3 finance worklist: paid orders flagged refund_required, not refunded yet. */
  needs_refund?: 1
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

/**
 * O5 — one Idempotency-Key per refund dialog session: a double click or retry re-sends the same key and the
 * server answers with the refund it already created instead of refunding twice.
 */
export function useRefundOrder(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ input, idempotencyKey }: { input: RefundOrderInput; idempotencyKey: string }) => {
      await api.post(`/admin/v1/orders/${id}/refund`, input, { headers: { 'Idempotency-Key': idempotencyKey } })
    },
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['order', id] })
      qc.invalidateQueries({ queryKey: ['orders'] })
      qc.invalidateQueries({ queryKey: ['refunds'] })
    },
  })
}
