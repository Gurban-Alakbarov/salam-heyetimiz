import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { useSubscriptions } from '@/api/subscriptions'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatMoney } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { SubscriptionTier } from '@/types/api'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'active', label: 'Aktiv' },
  { value: 'pending_payment', label: 'Ödəniş gözləyir' },
  { value: 'expired', label: 'Bitib' },
  { value: 'cancelled', label: 'Ləğv edilib' },
  { value: 'refunded', label: 'Geri qaytarılıb' },
]
const tierLabels: Record<SubscriptionTier, string> = { main: 'Əsas', additional: 'Əlavə' }

export function SubscriptionsPage() {
  const [status, setStatus] = useState('')
  const [tier, setTier] = useState('')
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  useEffect(() => {
    reset()
  }, [status, tier, reset])

  const query = useSubscriptions({ status: status || undefined, tier: tier || undefined, cursor, limit: 25 })
  const rows = query.data?.data ?? []

  return (
    <div className="space-y-5">
      <PageHeader title="Abunəliklər" description="Abunəliklərin siyahısı və statusu" />

      <div className="flex flex-col gap-3 sm:flex-row">
        <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </Select>
        <Select className="sm:w-44" value={tier} onChange={(e) => setTier(e.target.value)}>
          <option value="">Bütün tariflər</option>
          <option value="main">Əsas</option>
          <option value="additional">Əlavə</option>
        </Select>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={7} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Abunəlik tapılmadı" />
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>ID</TableHead>
                  <TableHead>Tarif</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Cihaz</TableHead>
                  <TableHead>Qiymət</TableHead>
                  <TableHead>Bitir</TableHead>
                  <TableHead>Qalıb</TableHead>
                  <TableHead>Avto</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((s) => (
                  <TableRow key={s.id}>
                    <TableCell className="font-medium">#{s.id}</TableCell>
                    <TableCell>{tierLabels[s.tier] ?? s.tier}</TableCell>
                    <TableCell>
                      <StatusBadge kind="subscription" value={s.status} />
                    </TableCell>
                    <TableCell>
                      {s.device_id ? (
                        <Link to={`/devices/${s.device_id}`} className="text-primary hover:underline">
                          #{s.device_id}
                        </Link>
                      ) : (
                        '—'
                      )}
                    </TableCell>
                    <TableCell className="text-muted-foreground">{formatMoney(s.price_minor, s.currency)}</TableCell>
                    <TableCell className="text-muted-foreground">{formatDate(s.ends_at)}</TableCell>
                    <TableCell className="text-muted-foreground">
                      {s.days_remaining !== null ? `${s.days_remaining} gün` : '—'}
                    </TableCell>
                    <TableCell>
                      <Badge variant={s.auto_renew ? 'success' : 'muted'}>{s.auto_renew ? 'Bəli' : 'Xeyr'}</Badge>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <CursorPagination
              pageIndex={pageIndex}
              canPrev={canPrev}
              hasMore={query.data?.page.has_more ?? false}
              onPrev={prev}
              onNext={() => next(query.data?.page.next_cursor ?? null)}
              disabled={query.isFetching}
            />
          </>
        )}
      </Card>
    </div>
  )
}
