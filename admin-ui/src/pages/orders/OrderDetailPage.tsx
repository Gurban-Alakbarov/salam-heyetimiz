import { type FormEvent, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ChevronLeft, Loader2, RefreshCw, Undo2 } from 'lucide-react'
import { useOrder, useRecheckOrder, useRefundOrder } from '@/api/orders'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState, LoadingState } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import { formatDateTime, formatMoney } from '@/lib/format'
import { purposeLabels } from '@/lib/labels'
import type { Order } from '@/types/api'

interface OrderPaymentRow {
  id: number
  type: string
  status: string
  amount_minor: number
  bank_transaction_id?: string | null
  card_brand?: string | null
  card_last4?: string | null
  occurred_at?: string | null
}
interface OrderWebhook { id: number; bank_status: string; outcome: string; created_at: string | null; processed_at: string | null }
interface OrderTimelineEntry {
  at: string | null
  event: string
  description?: string
  detail: string | null
  actor?: string
  source?: string
  status?: string
  color?: string
}
interface PaymentDetail {
  merchant_name?: string | null
  merchant_id?: string | null
  terminal_id?: string | null
  payment_method?: string | null
  card_last4?: string | null
  approval_code?: string | null
  rrn?: string | null
  bank_transaction_id?: string | null
  bank_order_id?: string | null
  idempotency_key?: string | null
  webhook_status?: string | null
  last_sync_at?: string | null
}
interface OrderDetailExtra extends Order {
  captured_minor?: number
  refunded_minor?: number
  refundable_minor?: number
  payment_detail?: PaymentDetail
  payments?: OrderPaymentRow[]
  webhooks?: OrderWebhook[]
  timeline?: OrderTimelineEntry[]
}

function Summary({ order }: { order: OrderDetailExtra }) {
  const isPaidLike = order.status === 'paid' || order.status === 'partially_refunded'
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Sifariş & ödəniş məlumatı</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3">
        <Cell label="Referans" value={order.reference} />
        <Cell label="Status" value={<StatusBadge kind="order" value={order.status} />} />
        <Cell label="Məqsəd" value={purposeLabels[order.purpose] ?? order.purpose} />
        <Cell label="Məbləğ" value={formatMoney(order.amount_minor, order.currency)} />
        <Cell label="Ödənilib" value={formatDateTime(order.paid_at)} />
        <Cell label="Yaradılıb" value={formatDateTime(order.created_at)} />
        {order.bank_order_id && <Cell label="Bank Payment ID" value={<code className="text-xs">{order.bank_order_id}</code>} />}
        {isPaidLike && order.captured_minor != null && <Cell label="Tutulmuş (captured)" value={formatMoney(order.captured_minor, order.currency)} />}
        {isPaidLike && order.refunded_minor != null && <Cell label="Geri qaytarılmış" value={formatMoney(order.refunded_minor, order.currency)} />}
        {isPaidLike && order.refundable_minor != null && <Cell label="Qalan geri qaytarıla bilən" value={<strong>{formatMoney(order.refundable_minor, order.currency)}</strong>} />}
        {order.failed_reason && <Cell label="Uğursuzluq səbəbi" value={order.failed_reason} />}
      </CardContent>
    </Card>
  )
}

function Cell({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  )
}

const ACTOR_LABEL: Record<string, string> = { system: 'Sistem', admin: 'Admin', customer: 'Müştəri', bank: 'Bank' }
const SOURCE_LABEL: Record<string, string> = { app: 'Tətbiq', birpay: 'BirPay', webhook: 'Webhook', admin: 'Admin' }
const DOT_COLOR: Record<string, string> = {
  blue: 'bg-blue-500', indigo: 'bg-indigo-500', purple: 'bg-purple-500',
  green: 'bg-emerald-500', amber: 'bg-amber-500', red: 'bg-red-500',
}

function PaymentInfo({ order }: { order: OrderDetailExtra }) {
  const d = order.payment_detail
  if (!d) return null
  return (
    <Card>
      <CardHeader className="pb-2"><CardTitle className="text-base">Ödəniş məlumatı</CardTitle></CardHeader>
      <CardContent className="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3">
        <Cell label="Merchant" value={d.merchant_name ?? d.merchant_id} />
        <Cell label="Merchant ID" value={d.merchant_id ? <code className="text-xs">{d.merchant_id}</code> : null} />
        <Cell label="Terminal ID" value={d.terminal_id ? <code className="text-xs">{d.terminal_id}</code> : null} />
        <Cell label="Ödəniş metodu" value={d.payment_method ? `${d.payment_method}${d.card_last4 ? ' ••' + d.card_last4 : ''}` : null} />
        <Cell label="Approval Code" value={d.approval_code} />
        <Cell label="RRN" value={d.rrn ? <code className="text-xs">{d.rrn}</code> : null} />
        <Cell label="Bank Transaction ID" value={d.bank_transaction_id ? <code className="text-xs">{d.bank_transaction_id}</code> : null} />
        <Cell label="BirPay Payment ID" value={d.bank_order_id ? <code className="text-xs">{d.bank_order_id}</code> : null} />
        <Cell label="Idempotency Key" value={d.idempotency_key ? <code className="break-all text-xs">{d.idempotency_key}</code> : null} />
        <Cell label="Webhook statusu" value={d.webhook_status} />
        <Cell label="Son sinxronizasiya" value={formatDateTime(d.last_sync_at ?? null)} />
        <Cell
          label="Qalan geri qaytarıla bilən"
          value={order.refundable_minor != null ? <strong>{formatMoney(order.refundable_minor, order.currency)}</strong> : null}
        />
      </CardContent>
    </Card>
  )
}

export function OrderDetailPage() {
  const { id } = useParams()
  const orderId = Number(id)
  const { toast } = useToast()
  const { data: order, isLoading, isError, error, refetch } = useOrder(orderId)
  const recheck = useRecheckOrder(orderId)
  const refund = useRefundOrder(orderId)
  const [refundOpen, setRefundOpen] = useState(false)

  const onRecheck = () => {
    recheck.mutate(undefined, {
      onSuccess: () => toast({ variant: 'success', title: 'Sifariş yenidən yoxlanıldı' }),
      onError: (err) =>
        toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <div className="space-y-6">
      <Link to="/orders" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ChevronLeft className="h-4 w-4" />
        Sifarişlər
      </Link>

      {isLoading ? (
        <LoadingState />
      ) : isError || !order ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : (
        <>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 className="text-2xl font-semibold tracking-tight">{order.reference}</h1>
            <div className="flex flex-wrap gap-2">
              <Button variant="outline" onClick={onRecheck} disabled={recheck.isPending}>
                {recheck.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                Yenidən yoxla
              </Button>
              {(order.status === 'paid' || order.status === 'partially_refunded') && (
                <PermissionGate anyOf={[PERM.refundsCreate]}>
                  <Button variant="destructive" onClick={() => setRefundOpen(true)}>
                    <Undo2 className="h-4 w-4" />
                    Geri qaytar
                  </Button>
                </PermissionGate>
              )}
            </div>
          </div>

          <Summary order={order} />

          <PaymentInfo order={order as OrderDetailExtra} />

          {order.items && order.items.length > 0 && (
            <Card className="overflow-hidden">
              <CardHeader className="pb-2">
                <CardTitle className="text-base">Elementlər</CardTitle>
              </CardHeader>
              <CardContent className="px-0 pb-0">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Növ</TableHead>
                      <TableHead>Təsvir</TableHead>
                      <TableHead>Say</TableHead>
                      <TableHead>Vahid</TableHead>
                      <TableHead>Cəm</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {order.items.map((item) => (
                      <TableRow key={item.id}>
                        <TableCell className="text-muted-foreground">{item.item_type}</TableCell>
                        <TableCell>{item.description ?? '—'}</TableCell>
                        <TableCell className="text-muted-foreground">{item.quantity}</TableCell>
                        <TableCell className="text-muted-foreground">{formatMoney(item.unit_amount_minor, order.currency)}</TableCell>
                        <TableCell>{formatMoney(item.total_amount_minor, order.currency)}</TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}

          <PaymentHistory order={order as OrderDetailExtra} />

          <RefundDialog
            open={refundOpen}
            onOpenChange={setRefundOpen}
            order={order as OrderDetailExtra}
            loading={refund.isPending}
            error={refund.error instanceof ApiError ? refund.error.message : undefined}
            onSubmit={(payload) =>
              refund.mutate(payload, {
                onSuccess: () => {
                  toast({ variant: 'success', title: 'Geri qaytarma sorğulandı' })
                  setRefundOpen(false)
                },
                onError: (err) =>
                  toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
              })
            }
          />
        </>
      )}
    </div>
  )
}

function PaymentHistory({ order }: { order: OrderDetailExtra }) {
  const payments = order.payments ?? []
  const webhooks = order.webhooks ?? []
  const timeline = order.timeline ?? []
  if (payments.length === 0 && webhooks.length === 0 && timeline.length === 0) return null

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      {payments.length > 0 && (
        <Card className="overflow-hidden lg:col-span-2">
          <CardHeader className="pb-2"><CardTitle className="text-base">Ödənişlər (RRN / approval)</CardTitle></CardHeader>
          <CardContent className="px-0 pb-0">
            <Table>
              <TableHeader><TableRow><TableHead>Növ</TableHead><TableHead>Status</TableHead><TableHead>Məbləğ</TableHead><TableHead>RRN / Tx ID</TableHead><TableHead>Kart</TableHead><TableHead>Vaxt</TableHead></TableRow></TableHeader>
              <TableBody>
                {payments.map((p) => (
                  <TableRow key={p.id}>
                    <TableCell className="text-muted-foreground">{p.type}</TableCell>
                    <TableCell className="text-muted-foreground">{p.status}</TableCell>
                    <TableCell>{formatMoney(p.amount_minor, order.currency)}</TableCell>
                    <TableCell className="font-mono text-xs">{p.bank_transaction_id ?? '—'}</TableCell>
                    <TableCell className="text-muted-foreground">{p.card_brand ? `${p.card_brand} ••${p.card_last4 ?? ''}` : '—'}</TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(p.occurred_at ?? null)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      )}

      <Card>
        <CardHeader className="pb-2"><CardTitle className="text-base">Webhook tarixçəsi</CardTitle></CardHeader>
        <CardContent className="space-y-2 text-sm">
          {webhooks.length === 0 ? <p className="text-muted-foreground">Webhook yoxdur.</p> : webhooks.map((w) => (
            <div key={w.id} className="flex items-center justify-between gap-2 border-b pb-1 last:border-0">
              <span><code className="text-xs">{w.bank_status}</code> <span className="text-muted-foreground">· {w.outcome}</span></span>
              <span className="text-xs text-muted-foreground">{formatDateTime(w.created_at)}</span>
            </div>
          ))}
        </CardContent>
      </Card>

      <Card className="lg:col-span-2">
        <CardHeader className="pb-2"><CardTitle className="text-base">Hadisə xronologiyası</CardTitle></CardHeader>
        <CardContent>
          {timeline.length === 0 ? (
            <p className="text-sm text-muted-foreground">Hadisə yoxdur.</p>
          ) : (
            <ol className="relative space-y-4 border-l border-border pl-5">
              {timeline.map((t, i) => (
                <li key={i} className="relative">
                  <span
                    className={`absolute -left-[1.45rem] top-1 h-2.5 w-2.5 rounded-full ring-2 ring-background ${DOT_COLOR[t.color ?? ''] ?? 'bg-muted-foreground'}`}
                  />
                  <div className="flex flex-col gap-0.5 sm:flex-row sm:items-center sm:justify-between">
                    <span className="text-sm font-medium">{t.description ?? t.event}</span>
                    <span className="text-xs text-muted-foreground">{formatDateTime(t.at)}</span>
                  </div>
                  <div className="text-xs text-muted-foreground">
                    {[
                      t.actor ? (ACTOR_LABEL[t.actor] ?? t.actor) : null,
                      t.source ? (SOURCE_LABEL[t.source] ?? t.source) : null,
                      t.status,
                    ]
                      .filter(Boolean)
                      .join(' · ')}
                    {t.detail ? <span className="font-mono"> · {t.detail}</span> : null}
                  </div>
                </li>
              ))}
            </ol>
          )}
        </CardContent>
      </Card>
    </div>
  )
}

function RefundDialog({
  open,
  onOpenChange,
  order,
  loading,
  error,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  order: OrderDetailExtra
  loading: boolean
  error?: string
  onSubmit: (payload: { amount_minor: number; reason: string }) => void
}) {
  const remainingMinor = order.refundable_minor ?? order.amount_minor
  const remaining = (remainingMinor / 100).toFixed(2)
  const refundedMinor = order.refunded_minor ?? 0
  const [amount, setAmount] = useState(remaining)
  const [reason, setReason] = useState('')
  const [confirm, setConfirm] = useState(false)

  const amountMinor = Math.round(Number(amount) * 100)
  const invalid = !(amountMinor > 0) || amountMinor > remainingMinor || !reason.trim()

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (invalid || !confirm) return
    onSubmit({ amount_minor: amountMinor, reason: reason.trim() })
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (loading) return
        onOpenChange(next)
        if (!next) { setAmount(remaining); setReason(''); setConfirm(false) }
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>Geri qaytarma</DialogTitle>
            <DialogDescription>{order.reference}</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="grid grid-cols-3 gap-2 rounded-md bg-muted/50 p-3 text-sm">
              <div><div className="text-xs text-muted-foreground">İlkin ödəniş</div><div className="font-medium">{formatMoney(order.captured_minor ?? order.amount_minor, order.currency)}</div></div>
              <div><div className="text-xs text-muted-foreground">Geri qaytarılmış</div><div className="font-medium">{formatMoney(refundedMinor, order.currency)}</div></div>
              <div><div className="text-xs text-muted-foreground">Qalan</div><div className="font-medium text-emerald-600">{formatMoney(remainingMinor, order.currency)}</div></div>
            </div>
            <div className="space-y-2">
              <Label htmlFor="refund-amount">Geri qaytarma məbləği ({order.currency})</Label>
              <Input id="refund-amount" type="number" step="0.01" min="0.01" max={remaining} value={amount} onChange={(e) => setAmount(e.target.value)} required />
              {amountMinor > remainingMinor && <p className="text-xs text-destructive">Qalan məbləğdən çox ola bilməz.</p>}
            </div>
            <div className="space-y-2">
              <Label htmlFor="refund-reason">Səbəb</Label>
              <Textarea id="refund-reason" value={reason} onChange={(e) => setReason(e.target.value)} required />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={confirm} onChange={(e) => setConfirm(e.target.checked)} />
              {formatMoney(amountMinor || 0, order.currency)} geri qaytarmanı təsdiq edirəm
            </label>
            {error && <p className="rounded-md bg-destructive/10 p-2 text-sm text-destructive">{error}</p>}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>İmtina</Button>
            <Button type="submit" variant="destructive" disabled={loading || invalid || !confirm}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Geri qaytar
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
