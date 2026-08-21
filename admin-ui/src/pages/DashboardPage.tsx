import { Link } from 'react-router-dom'
import { ArrowRight, Wifi, WifiOff } from 'lucide-react'
import { useDevices } from '@/api/devices'
import { useOrders } from '@/api/orders'
import { useRefunds } from '@/api/refunds'
import { type PaymentStats, usePaymentStats } from '@/api/stats'
import { useSubscriptions } from '@/api/subscriptions'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime, formatMoney, relativeTime } from '@/lib/format'
import { isLive } from '@/lib/online'


function SectionCard({
  title,
  to,
  children,
}: {
  title: string
  to: string
  children: React.ReactNode
}) {
  return (
    <Card>
      <CardHeader className="flex-row items-center justify-between space-y-0 pb-3">
        <CardTitle className="text-base">{title}</CardTitle>
        <Link to={to} className="inline-flex items-center gap-1 text-xs font-medium text-primary hover:underline">
          Hamısına bax
          <ArrowRight className="h-3 w-3" />
        </Link>
      </CardHeader>
      <CardContent className="pt-0">{children}</CardContent>
    </Card>
  )
}

function fmtDur(seconds: number | null): string {
  if (seconds == null) return '—'
  if (seconds < 60) return `${seconds}s`
  if (seconds < 3600) return `${Math.floor(seconds / 60)}d ${seconds % 60}s`
  return `${Math.floor(seconds / 3600)}s ${Math.floor((seconds % 3600) / 60)}d`
}

function Kpi({ label, value, sub, accent }: { label: string; value: string; sub?: string; accent?: string }) {
  return (
    <Card>
      <CardContent className="space-y-1 p-4">
        <div className="text-xs uppercase tracking-wide text-muted-foreground">{label}</div>
        <div className={`text-xl font-semibold tabular-nums ${accent ?? ''}`}>{value}</div>
        {sub && <div className="text-xs text-muted-foreground">{sub}</div>}
      </CardContent>
    </Card>
  )
}

function PaymentStatsBlock({ stats }: { stats: PaymentStats }) {
  const periods: Array<[string, keyof PaymentStats['periods']]> = [
    ['Bu gün', 'today'],
    ['Dünən', 'yesterday'],
    ['Bu həftə', 'this_week'],
    ['Bu ay', 'this_month'],
  ]
  const t = stats.totals
  return (
    <div className="space-y-4">
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {periods.map(([label, key]) => (
          <Kpi
            key={key}
            label={label}
            value={formatMoney(stats.periods[key].amount_minor, 'AZN')}
            sub={`${stats.periods[key].count} ödəniş`}
          />
        ))}
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Kpi label="Ümumi ödənilmiş" value={formatMoney(t.total_paid_minor, 'AZN')} sub={`${t.paid} sifariş`} accent="text-emerald-600" />
        <Kpi label="Ümumi qaytarılmış" value={formatMoney(t.total_refunded_minor, 'AZN')} sub={`${t.refunded + t.partially_refunded} geri qaytarma`} accent="text-amber-600" />
        <Kpi label="Uğur faizi" value={stats.rates.success_rate != null ? `${stats.rates.success_rate}%` : '—'} sub="paid / yekun" />
        <Kpi label="Geri qaytarma faizi" value={stats.rates.refund_rate != null ? `${stats.rates.refund_rate}%` : '—'} sub="refunded / paid" />
      </div>
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
        <Kpi label="Gözləyir" value={String(t.pending + t.authorising)} />
        <Kpi label="Uğursuz" value={String(t.failed)} accent="text-red-600" />
        <Kpi label="Ləğv edilib" value={String(t.cancelled)} />
        <Kpi label="Qismən qaytarılıb" value={String(t.partially_refunded)} />
        <Kpi label="Orta ödəniş vaxtı" value={fmtDur(stats.avg_payment_seconds)} />
        <Kpi label="Orta refund vaxtı" value={fmtDur(stats.avg_refund_seconds)} />
      </div>
    </div>
  )
}

export function DashboardPage() {
  const stats = usePaymentStats()
  const devices = useDevices({ limit: 5 })
  const subscriptions = useSubscriptions({ status: 'active', limit: 5 })
  const orders = useOrders({ limit: 5 })
  const refunds = useRefunds({ limit: 5 })

  return (
    <div className="space-y-6">
      <PageHeader title="İdarə paneli" description="Ödəniş statistikası və son fəaliyyət" />

      {stats.data && <PaymentStatsBlock stats={stats.data} />}

      <div className="grid gap-4 lg:grid-cols-2">
        <SectionCard title="Son cihazlar" to="/devices">
          {devices.isLoading ? (
            <LoadingState />
          ) : devices.isError ? (
            <ErrorState error={devices.error} onRetry={() => devices.refetch()} />
          ) : devices.data && devices.data.data.length > 0 ? (
            <ul className="divide-y">
              {devices.data.data.map((d) => (
                <li key={d.id} className="flex items-center justify-between py-2.5 text-sm">
                  <Link to={`/devices/${d.id}`} className="font-medium hover:underline">
                    {d.serial}
                  </Link>
                  <div className="flex items-center gap-2">
                    {isLive(d.last_online_at) ? (
                      <span className="inline-flex items-center gap-1 text-xs text-success">
                        <Wifi className="h-3.5 w-3.5" /> Onlayn
                      </span>
                    ) : (
                      <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                        <WifiOff className="h-3.5 w-3.5" /> Oflayn
                      </span>
                    )}
                    <StatusBadge kind="device" value={d.status} />
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState />
          )}
        </SectionCard>

        <SectionCard title="Aktiv abunəliklər" to="/subscriptions">
          {subscriptions.isLoading ? (
            <LoadingState />
          ) : subscriptions.isError ? (
            <ErrorState error={subscriptions.error} onRetry={() => subscriptions.refetch()} />
          ) : subscriptions.data && subscriptions.data.data.length > 0 ? (
            <ul className="divide-y">
              {subscriptions.data.data.map((s) => (
                <li key={s.id} className="flex items-center justify-between py-2.5 text-sm">
                  <span className="font-medium">#{s.id}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">
                      {s.days_remaining !== null ? `${s.days_remaining} gün qalıb` : '—'}
                    </span>
                    <StatusBadge kind="subscription" value={s.status} />
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="Aktiv abunəlik yoxdur" />
          )}
        </SectionCard>

        <SectionCard title="Son sifarişlər" to="/orders">
          {orders.isLoading ? (
            <LoadingState />
          ) : orders.isError ? (
            <ErrorState error={orders.error} onRetry={() => orders.refetch()} />
          ) : orders.data && orders.data.data.length > 0 ? (
            <ul className="divide-y">
              {orders.data.data.map((o) => (
                <li key={o.id} className="flex items-center justify-between py-2.5 text-sm">
                  <Link to={`/orders/${o.id}`} className="font-medium hover:underline">
                    {o.reference}
                  </Link>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground">{formatMoney(o.amount_minor, o.currency)}</span>
                    <StatusBadge kind="order" value={o.status} />
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="Sifariş yoxdur" />
          )}
        </SectionCard>

        <SectionCard title="Son geri qaytarmalar" to="/refunds">
          {refunds.isLoading ? (
            <LoadingState />
          ) : refunds.isError ? (
            <ErrorState error={refunds.error} onRetry={() => refunds.refetch()} />
          ) : refunds.data && refunds.data.data.length > 0 ? (
            <ul className="divide-y">
              {refunds.data.data.map((r) => (
                <li key={r.id} className="flex items-center justify-between py-2.5 text-sm">
                  <span className="font-medium">Sifariş #{r.order_id}</span>
                  <div className="flex items-center gap-2">
                    <span className="text-xs text-muted-foreground" title={formatDateTime(r.created_at)}>
                      {relativeTime(r.created_at)}
                    </span>
                    <StatusBadge kind="refund" value={r.status} />
                  </div>
                </li>
              ))}
            </ul>
          ) : (
            <EmptyState title="Geri qaytarma yoxdur" />
          )}
        </SectionCard>
      </div>
    </div>
  )
}
