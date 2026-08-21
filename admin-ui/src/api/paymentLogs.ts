import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { Paginated } from '@/types/api'

export interface PaymentLogEntry {
  id: number
  order_id: number | null
  direction: 'outbound' | 'inbound'
  endpoint: string
  http_method: string | null
  http_status: number | null
  signature_provided: boolean | null
  signature_valid: boolean | null
  ip: string | null
  latency_ms: number | null
  request: unknown
  response: unknown
  created_at: string | null
}

export interface PaymentLogParams {
  direction?: string
  signature?: string
  endpoint?: string
  errors?: string
  order_id?: number
  limit?: number
  cursor?: string | null
}

export function usePaymentLogs(params: PaymentLogParams) {
  return useQuery({
    queryKey: ['payment-logs', params],
    queryFn: async () =>
      (await api.get<Paginated<PaymentLogEntry>>('/admin/v1/payment-logs', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}
