import { type FormEvent, useEffect, useMemo, useState } from 'react'
import { Loader2, Plus, Trash2 } from 'lucide-react'
import { useComplexes } from '@/api/admins'
import { useDevices } from '@/api/devices'
import {
  type PriceScope,
  type SubscriptionPriceRow,
  useRemoveSubscriptionPrice,
  useSetSubscriptionPrice,
  useSubscriptionPrices,
} from '@/api/subscriptionPrices'
import { useMobileUsers } from '@/api/users'
import { PageHeader } from '@/components/PageHeader'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime, formatMoney } from '@/lib/format'

const SCOPE_LABELS: Record<PriceScope, string> = { user: 'Şəxs', device: 'Cihaz', complex: 'Kompleks' }

/** People, devices or complexes matching the search, as options for the subject select. */
function useSubjectOptions(scope: PriceScope, q: string, enabled: boolean) {
  const users = useMobileUsers({ q: scope === 'user' && enabled ? q : undefined, per_page: 20 })
  const devices = useDevices({ q: scope === 'device' && enabled ? q : undefined, limit: 20 })
  const complexes = useComplexes()

  return useMemo(() => {
    if (scope === 'user') {
      return (users.data?.data ?? []).map((u) => ({ id: u.id, label: u.full_name || u.phone || u.email || `#${u.id}` }))
    }
    if (scope === 'device') {
      return (devices.data?.data ?? []).map((d) => ({ id: d.id, label: d.location_label || d.serial }))
    }
    const needle = q.trim().toLowerCase()
    return (complexes.data ?? [])
      .filter((c) => needle === '' || c.name.toLowerCase().includes(needle))
      .map((c) => ({ id: c.id, label: c.name }))
  }, [scope, q, users.data, devices.data, complexes.data])
}

function PriceDialog({ open, onOpenChange, row }: { open: boolean; onOpenChange: (o: boolean) => void; row: SubscriptionPriceRow | null }) {
  const { toast } = useToast()
  const save = useSetSubscriptionPrice()
  const [scope, setScope] = useState<PriceScope>('user')
  const [q, setQ] = useState('')
  const [subjectId, setSubjectId] = useState('')
  const [azn, setAzn] = useState('')
  const options = useSubjectOptions(scope, q, open && row === null)

  useEffect(() => {
    if (!open) return
    setScope(row?.scope ?? 'user')
    setQ('')
    setSubjectId(row ? String(row.subject_id) : '')
    setAzn(row ? (row.price_minor / 100).toFixed(2) : '')
  }, [open, row])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const priceMinor = Math.round(Number.parseFloat(azn.replace(',', '.')) * 100)
    if (!subjectId || !Number.isFinite(priceMinor)) return
    save.mutate({ scope, subject_id: Number(subjectId), price_minor: priceMinor }, {
      onSuccess: () => {
        toast({ variant: 'success', title: 'Qiymət saxlanıldı' })
        onOpenChange(false)
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <form onSubmit={submit} className="space-y-4">
          <DialogHeader>
            <DialogTitle>{row ? 'Qiyməti dəyiş' : 'Xüsusi qiymət'}</DialogTitle>
            <DialogDescription>Yalnız növbəti ödənişdən keçərlidir. Aktiv abunəliklər və açıq sifarişlər dəyişmir.</DialogDescription>
          </DialogHeader>

          {row ? (
            <p className="text-sm">{SCOPE_LABELS[row.scope]}: <span className="font-medium">{row.subject_label ?? `#${row.subject_id}`}</span></p>
          ) : (
            <>
              <div className="space-y-1.5">
                <Label htmlFor="price-scope">Nəyə aiddir</Label>
                <Select id="price-scope" value={scope} onChange={(e) => { setScope(e.target.value as PriceScope); setSubjectId('') }}>
                  {Object.entries(SCOPE_LABELS).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="price-search">Axtar</Label>
                <Input id="price-search" placeholder="Ad, telefon və ya seriya nömrəsi" value={q} onChange={(e) => { setQ(e.target.value); setSubjectId('') }} />
              </div>
              <div className="space-y-1.5">
                <Label htmlFor="price-subject">Seçin</Label>
                <Select id="price-subject" value={subjectId} onChange={(e) => setSubjectId(e.target.value)}>
                  <option value="">— seçin —</option>
                  {options.map((o) => <option key={o.id} value={o.id}>{o.label}</option>)}
                </Select>
              </div>
            </>
          )}

          <div className="space-y-1.5">
            <Label htmlFor="price-amount">Qiymət (illik)</Label>
            <div className="relative">
              <Input id="price-amount" type="number" inputMode="decimal" step="0.01" min="1" max="1000" value={azn} onChange={(e) => setAzn(e.target.value)} className="pr-14" />
              <span className="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted-foreground">AZN</span>
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>Ləğv et</Button>
            <Button type="submit" disabled={save.isPending || !subjectId || !azn}>
              {save.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Yadda saxla
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

export function SubscriptionPricesPage() {
  const { toast } = useToast()
  const query = useSubscriptionPrices()
  const remove = useRemoveSubscriptionPrice()
  const [dialog, setDialog] = useState<{ open: boolean; row: SubscriptionPriceRow | null }>({ open: false, row: null })
  const rows = query.data ?? []

  const drop = (row: SubscriptionPriceRow) => {
    if (!window.confirm(`${row.subject_label ?? `#${row.subject_id}`} üçün xüsusi qiymət silinsin? Növbəti ödənişdən standart qiymət keçərli olacaq.`)) return
    remove.mutate(row.id, {
      onSuccess: () => toast({ variant: 'success', title: 'Xüsusi qiymət silindi' }),
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <div className="space-y-5">
      <PageHeader
        title="Xüsusi qiymətlər"
        description="Şəxs, cihaz və ya kompleks üçün illik abunə qiyməti. Üstünlük: şəxs, sonra cihaz, sonra kompleks; qalanlarına standart qiymət tətbiq olunur."
        actions={<Button onClick={() => setDialog({ open: true, row: null })}><Plus className="mr-2 h-4 w-4" />Qiymət təyin et</Button>}
      />

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={5} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Xüsusi qiymət yoxdur" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Növ</TableHead>
                <TableHead>Ad</TableHead>
                <TableHead>Qiymət</TableHead>
                <TableHead>Dəyişib</TableHead>
                <TableHead className="w-44" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((row) => (
                <TableRow key={row.id}>
                  <TableCell>{SCOPE_LABELS[row.scope]}</TableCell>
                  <TableCell className="font-medium">{row.subject_label ?? `#${row.subject_id}`}</TableCell>
                  <TableCell>{formatMoney(row.price_minor)}</TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(row.updated_at)}</TableCell>
                  <TableCell className="text-right">
                    <Button variant="outline" size="sm" onClick={() => setDialog({ open: true, row })}>Dəyiş</Button>
                    <Button variant="ghost" size="sm" className="ml-1" aria-label="Sil" onClick={() => drop(row)} disabled={remove.isPending}><Trash2 className="h-4 w-4" /></Button>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      <PriceDialog open={dialog.open} onOpenChange={(open) => setDialog((d) => ({ ...d, open }))} row={dialog.row} />
    </div>
  )
}
