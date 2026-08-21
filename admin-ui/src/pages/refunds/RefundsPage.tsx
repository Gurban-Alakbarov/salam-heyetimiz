import { Fragment, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { ChevronDown, ChevronRight, Download } from 'lucide-react'
import { type AdminRefund, fetchRefundsForExport, useRefunds } from '@/api/refunds'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime, formatMoney } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'requested', label: 'Sorğulanıb' },
  { value: 'processing', label: 'İcra olunur' },
  { value: 'approved', label: 'Təsdiqlənib' },
  { value: 'rejected', label: 'Rədd edilib' },
  { value: 'failed', label: 'Uğursuz' },
]

export function RefundsPage() {
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [since, setSince] = useState('')
  const [until, setUntil] = useState('')
  const [exporting, setExporting] = useState(false)
  const [open, setOpen] = useState<number | null>(null)
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  const filters = { status: status || undefined, q: q || undefined, since: since || undefined, until: until || undefined }

  useEffect(() => { reset() }, [status, q, since, until, reset])

  const query = useRefunds({ ...filters, cursor, limit: 25 })
  const rows = query.data?.data ?? []

  const onExport = async () => {
    setExporting(true)
    try {
      const all = await fetchRefundsForExport(filters)
      downloadCsv(all)
    } finally {
      setExporting(false)
    }
  }

  return (
    <div className="space-y-5">
      <PageHeader title="Geri qaytarma tarixçəsi" description="Bütün geri qaytarmalar — axtarış, filtr, eksport" />

      <div className="flex flex-wrap items-end gap-2">
        <Select className="sm:w-48" value={status} onChange={(e) => setStatus(e.target.value)}>
          {STATUS_OPTIONS.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </Select>
        <Input className="sm:w-60" placeholder="Axtar (referans / bank id / səbəb)" value={q} onChange={(e) => setQ(e.target.value)} />
        <div><label className="text-xs text-muted-foreground">Başlanğıc</label><Input type="date" value={since} onChange={(e) => setSince(e.target.value)} /></div>
        <div><label className="text-xs text-muted-foreground">Son</label><Input type="date" value={until} onChange={(e) => setUntil(e.target.value)} /></div>
        <Button variant="outline" onClick={onExport} disabled={exporting}><Download className="mr-2 h-4 w-4" />{exporting ? 'Eksport...' : 'CSV'}</Button>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={8} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Geri qaytarma tapılmadı" />
        ) : (
          <>
            <div className="overflow-x-auto">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead className="w-8" />
                  <TableHead>ID</TableHead>
                  <TableHead>Sifariş</TableHead>
                  <TableHead>Müştəri</TableHead>
                  <TableHead className="text-right">Məbləğ</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Bank Refund ID</TableHead>
                  <TableHead>Admin</TableHead>
                  <TableHead>Yaradılıb</TableHead>
                  <TableHead>Tamamlanıb</TableHead>
                  <TableHead>Müddət</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((r) => (
                  <Fragment key={r.id}>
                    <TableRow className="cursor-pointer" onClick={() => setOpen(open === r.id ? null : r.id)}>
                      <TableCell className="text-muted-foreground">
                        {open === r.id ? <ChevronDown className="h-4 w-4" /> : <ChevronRight className="h-4 w-4" />}
                      </TableCell>
                      <TableCell className="font-medium">#{r.id}</TableCell>
                      <TableCell onClick={(e) => e.stopPropagation()}>
                        <Link to={`/orders/${r.order_id}`} className="text-primary hover:underline">{r.order_reference ?? `#${r.order_id}`}</Link>
                      </TableCell>
                      <TableCell className="text-muted-foreground">{r.customer ?? '—'}</TableCell>
                      <TableCell className="whitespace-nowrap text-right">{formatMoney(r.amount_minor, r.currency)}</TableCell>
                      <TableCell><StatusBadge kind="refund" value={r.status} />{r.error_message && <div className="text-xs text-destructive">{r.error_message}</div>}</TableCell>
                      <TableCell className="font-mono text-xs">{r.bank_refund_id ?? '—'}</TableCell>
                      <TableCell className="text-xs text-muted-foreground">{r.requested_by ?? `#${r.requested_by_admin_id}`}</TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(r.created_at)}</TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(r.completed_at)}</TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDuration(r.duration_seconds)}</TableCell>
                    </TableRow>
                    {open === r.id && (
                      <TableRow className="bg-muted/30 hover:bg-muted/30">
                        <TableCell colSpan={11} className="space-y-3 py-3">
                          <div className="grid gap-x-6 gap-y-1 sm:grid-cols-2">
                            <Field label="Səbəb" value={r.reason ?? '—'} />
                            <Field label="Emal edən" value={r.processed_by ?? '—'} />
                            <Field label="Bank Order ID" value={r.bank_order_id ?? '—'} mono />
                            <Field label="Xəta" value={r.error_message ?? '—'} />
                          </div>
                          <div>
                            <div className="mb-1 text-xs uppercase tracking-wide text-muted-foreground">Bank cavabı (raw)</div>
                            <pre className="max-h-64 overflow-auto rounded-md bg-background p-3 text-xs leading-relaxed">
                              {r.raw_response != null ? JSON.stringify(r.raw_response, null, 2) : '—'}
                            </pre>
                          </div>
                        </TableCell>
                      </TableRow>
                    )}
                  </Fragment>
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

function Field({ label, value, mono }: { label: string; value: string; mono?: boolean }) {
  return (
    <div className="flex flex-col gap-0.5 py-1">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className={`text-sm ${mono ? 'break-all font-mono text-xs' : ''}`}>{value}</span>
    </div>
  )
}

function formatDuration(seconds: number | null): string {
  if (seconds == null) return '—'
  if (seconds < 60) return `${seconds}s`
  return `${Math.floor(seconds / 60)}d ${seconds % 60}s`
}

function downloadCsv(rows: AdminRefund[]) {
  const head = ['id', 'order_reference', 'bank_order_id', 'bank_refund_id', 'amount', 'currency', 'status', 'customer', 'requested_by', 'processed_by', 'reason', 'created_at', 'completed_at']
  const esc = (v: unknown) => `"${String(v ?? '').replace(/"/g, '""')}"`
  const lines = [head.join(',')].concat(
    rows.map((r) => [r.id, r.order_reference, r.bank_order_id, r.bank_refund_id, (r.amount_minor / 100).toFixed(2), r.currency, r.status, r.customer, r.requested_by, r.processed_by, r.reason, r.created_at, r.completed_at].map(esc).join(',')),
  )
  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8' })
  const url = URL.createObjectURL(blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `refunds-${new Date().toISOString().slice(0, 10)}.csv`
  a.click()
  URL.revokeObjectURL(url)
}
