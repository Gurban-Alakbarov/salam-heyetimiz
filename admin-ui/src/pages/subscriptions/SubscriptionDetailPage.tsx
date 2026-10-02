import { Link, useParams } from 'react-router-dom'
import { ChevronLeft } from 'lucide-react'
import { useSubscription } from '@/api/subscriptions'
import { formatDateTime, formatMoney } from '@/lib/format'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const reasonLabels: Record<string, string> = {
  removed_by_komendant: 'Komendant sakini kompleksdən çıxarıb',
  removed_by_family_head: 'Ailə başçısı üzvü çıxarıb',
  abandoned_intent: 'Ödənilməmiş abunə cəhdi (avtomatik)',
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  )
}

/** Subscription detail (B11): legacy-comp badge, cancellation reason, payer ledger (payer ≠ beneficiary). */
export function SubscriptionDetailPage() {
  const { id } = useParams()
  const { data: s, isLoading, isError, error, refetch } = useSubscription(Number(id))

  return (
    <div className="space-y-6">
      <Link to="/subscriptions" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ChevronLeft className="h-4 w-4" /> Abunəliklər</Link>
      {isLoading ? <LoadingState /> : isError || !s ? <ErrorState error={error} onRetry={() => refetch()} /> : (
        <>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="text-2xl font-semibold tracking-tight">Abunəlik #{s.id}</h1>
            <StatusBadge kind="subscription" value={s.status} />
            {s.is_legacy_comp && <Badge variant="secondary" title="Köhnə pulsuz (comp) abunəlik — avtomatik ödənişə çevrilmir (BR-17)">Legacy comp</Badge>}
            {s.family_link_id !== null && <Badge variant="outline">Ailə üzvü</Badge>}
          </div>

          <Card>
            <CardContent className="grid grid-cols-2 gap-x-6 pt-4 sm:grid-cols-3 lg:grid-cols-4">
              <Row label="Tarif" value={s.tier === 'main' ? 'Əsas' : 'Əlavə'} />
              <Row label="Qiymət" value={`${formatMoney(s.price_minor, s.currency)} / ${s.term_days} gün`} />
              <Row label="Başlama" value={formatDateTime(s.starts_at)} />
              <Row label="Bitmə" value={formatDateTime(s.ends_at)} />
              <Row label="İstifadəçi (beneficiary)" value={s.beneficiary ? `${s.beneficiary.full_name ?? '—'} · ${s.beneficiary.phone ?? ''}` : '—'} />
              <Row label="Cihaz" value={s.device ? <Link to={`/devices/${s.device.id}`} className="text-primary hover:underline">{s.device.serial}</Link> : '—'} />
              <Row label="Cihaz rejimi" value={s.device ? (s.device.ownership_mode === 'complex' ? 'Kompleks' : 'Şəxsi') : '—'} />
              {s.status === 'cancelled' && (
                <>
                  <Row label="Ləğv tarixi" value={formatDateTime(s.cancelled_at)} />
                  <Row label="Ləğv səbəbi" value={s.cancellation_reason ? (reasonLabels[s.cancellation_reason] ?? s.cancellation_reason) : '—'} />
                </>
              )}
            </CardContent>
          </Card>

          <Card className="overflow-hidden">
            <CardHeader className="pb-2"><CardTitle className="text-base">Ödəniş dövrləri</CardTitle></CardHeader>
            <CardContent className="px-0 pb-0">
              {s.periods.length === 0 ? <p className="px-6 pb-6 text-sm text-muted-foreground">Ödənişli dövr yoxdur.</p> : (
                <Table>
                  <TableHeader><TableRow><TableHead>Növ</TableHead><TableHead>Dövr</TableHead><TableHead>Məbləğ</TableHead><TableHead>Ödəyən</TableHead><TableHead>Sifariş</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {s.periods.map((p) => (
                      <TableRow key={p.id}>
                        <TableCell>{p.kind}</TableCell>
                        <TableCell className="text-muted-foreground">{formatDateTime(p.period_start)} → {formatDateTime(p.period_end)}</TableCell>
                        <TableCell>{formatMoney(p.amount_minor, s.currency)}</TableCell>
                        <TableCell>
                          {p.paid_by_user_id === null ? '—' : p.paid_by_user_id === s.beneficiary?.id ? 'Özü' : <Badge variant="outline">İstifadəçi #{p.paid_by_user_id}</Badge>}
                        </TableCell>
                        <TableCell>{p.order_id ? <Link to={`/orders/${p.order_id}`} className="text-primary hover:underline">#{p.order_id}</Link> : '—'}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
