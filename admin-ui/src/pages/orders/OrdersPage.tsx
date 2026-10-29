import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Search } from 'lucide-react'
import { useOrders } from '@/api/orders'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatMoney } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { OrderStatus } from '@/types/api'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'paid', label: 'Ödənilib' },
  { value: 'pending', label: 'Gözləyir' },
  { value: 'authorising', label: 'Avtorizasiya' },
  { value: 'cancelled', label: 'Ləğv edilib' },
  { value: 'failed', label: 'Uğursuz' },
  { value: 'refunded', label: 'Geri qaytarılıb' },
  { value: 'partially_refunded', label: 'Qismən qaytarılıb' },
  { value: 'expired', label: 'Vaxtı keçib' },
]

/** Derived bank-side status label for the back-office view (the bank's lifecycle, not our order state). */
const BANK_STATUS: Record<string, string> = {
  paid: 'Pul tutulub',
  partially_refunded: 'Qismən qaytarılıb',
  refunded: 'Qaytarılıb',
  authorising: 'Bankda gözləyir',
  pending: 'Bankda gözləyir',
  cancelled: 'Bank ləğv edib',
  failed: 'Bank rədd edib',
  expired: 'Vaxtı bitib',
}

export function OrdersPage() {
  const [status, setStatus] = useState('')
  const [needsRefund, setNeedsRefund] = useState(false)
  const [search, setSearch] = useState('')
  const [q, setQ] = useState('')
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  // Debounce the search box so we don't fire a request per keystroke.
  useEffect(() => {
    const t = setTimeout(() => setQ(search.trim()), 350)
    return () => clearTimeout(t)
  }, [search])

  useEffect(() => {
    reset()
  }, [status, q, needsRefund, reset])

  const query = useOrders({
    status: status || undefined,
    q: q || undefined,
    needs_refund: needsRefund ? 1 : undefined,
    cursor,
    limit: 25,
  })
  const rows = query.data?.data ?? []

  return (
    <div className="space-y-5">
      <PageHeader title="Sifarişlər" description="Ödəniş sifarişləri" />

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative sm:w-96">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            className="pl-9"
            placeholder="Referans / BirPay ID / telefon / email üzrə axtar…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
          />
        </div>
        <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </Select>
        {/* K3: paid for access that no longer exists — finance refunds these by hand. */}
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            className="h-4 w-4 rounded border-input"
            checked={needsRefund}
            onChange={(e) => setNeedsRefund(e.target.checked)}
          />
          Yalnız refund gözləyənlər
        </label>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={8} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Sifariş tapılmadı" />
        ) : (
          <>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Sifariş #</TableHead>
                    <TableHead>Müştəri</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Bank statusu</TableHead>
                    <TableHead>BirPay ID</TableHead>
                    <TableHead className="text-right">Məbləğ</TableHead>
                    <TableHead className="text-right">Qaytarılıb</TableHead>
                    <TableHead className="text-right">Qalan</TableHead>
                    <TableHead>Yaradılıb</TableHead>
                    <TableHead>Yenilənib</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((o) => (
                    <TableRow key={o.id}>
                      <TableCell>
                        <Link to={`/orders/${o.id}`} className="font-medium text-primary hover:underline">
                          {o.reference}
                        </Link>
                        <div className="text-xs text-muted-foreground">#{o.id}</div>
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{o.customer ?? '—'}</TableCell>
                      <TableCell>
                        <div className="flex flex-wrap items-center gap-1">
                          <StatusBadge kind="order" value={o.status as OrderStatus} />
                          {o.refund_required && (
                            <Badge variant="destructive" title="Ödəniş gəlib, amma giriş artıq yoxdur — pulu geri qaytarın">
                              Pul qaytarılmalıdır
                            </Badge>
                          )}
                          {o.is_test && (
                            <Badge variant="destructive" title="Test ödənişi — real pul deyil">TEST</Badge>
                          )}
                          {o.late_captured_at && (
                            <Badge variant="warning" title="Bank sifarişin vaxtı bitdikdən sonra ödənişi təsdiqləyib">
                              Gec ödəniş
                            </Badge>
                          )}
                        </div>
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-xs text-muted-foreground">{BANK_STATUS[o.status] ?? '—'}</TableCell>
                      <TableCell className="max-w-[14rem] truncate font-mono text-xs text-muted-foreground" title={o.bank_order_id ?? ''}>
                        {o.bank_order_id ?? '—'}
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-right">{formatMoney(o.amount_minor, o.currency)}</TableCell>
                      <TableCell className="whitespace-nowrap text-right text-muted-foreground">
                        {o.refunded_minor ? formatMoney(o.refunded_minor, o.currency) : '—'}
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-right">
                        {o.refundable_minor != null ? formatMoney(o.refundable_minor, o.currency) : '—'}
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDate(o.created_at)}</TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDate(o.updated_at ?? null)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
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
