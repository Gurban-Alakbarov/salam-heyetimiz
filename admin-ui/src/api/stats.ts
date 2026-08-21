import { useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'

export interface PeriodStat {
  count: number
  amount_minor: number
}

export interface PaymentStats {
  periods: {
    today: PeriodStat
    yesterday: PeriodStat
    this_week: PeriodStat
    this_month: PeriodStat
  }
  totals: {
    paid: number
    pending: number
    authorising: number
    failed: number
    cancelled: number
    refunded: number
    partially_refunded: number
    total_paid_minor: number
    total_refunded_minor: number
  }
  rates: {
    success_rate: number | null
    refund_rate: number | null
  }
  avg_payment_seconds: number | null
  avg_refund_seconds: number | null
}

export function usePaymentStats() {
  return useQuery({
    queryKey: ['payment-stats'],
    queryFn: async () => (await api.get<PaymentStats>('/admin/v1/payments/stats')).data,
    staleTime: 60_000,
  })
}
