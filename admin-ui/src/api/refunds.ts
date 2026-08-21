import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { Paginated } from '@/types/api'

export interface AdminRefund {
  id: number
  order_id: number
  order_reference: string | null
  bank_order_id: string | null
  bank_refund_id: string | null
  amount_minor: number
  currency: string
  reason: string | null
  status: string
  requested_by_admin_id: number
  requested_by: string | null
  processed_by_admin_id: number | null
  processed_by: string | null
  customer: string | null
  error_message: string | null
  created_at: string | null
  completed_at: string | null
  duration_seconds: number | null
  raw_response: unknown
}

export interface RefundListParams {
  status?: string
  since?: string
  until?: string
  q?: string
  limit?: number
  cursor?: string | null
}

export function useRefunds(params: RefundListParams) {
  return useQuery({
    queryKey: ['refunds', params],
    queryFn: async () =>
      (await api.get<Paginated<AdminRefund>>('/admin/v1/refunds', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

/** Fetch all pages for CSV export (capped). */
export async function fetchRefundsForExport(params: RefundListParams): Promise<AdminRefund[]> {
  const out: AdminRefund[] = []
  let cursor: string | null = null
  for (let i = 0; i < 40; i++) {
    const page: Paginated<AdminRefund> = (await api.get<Paginated<AdminRefund>>('/admin/v1/refunds', {
      params: cleanParams({ ...params, cursor, limit: 100 }),
    })).data
    out.push(...page.data)
    if (!page.page.has_more || !page.page.next_cursor) break
    cursor = page.page.next_cursor
  }
  return out
}
