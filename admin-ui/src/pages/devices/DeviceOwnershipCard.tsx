import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import { useComplexDeviceBinding, useComplexList } from '@/api/complexes'
import { useChangeOwnershipMode, useUpdateDevice } from '@/api/devices'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { formatDateTime, formatMoney } from '@/lib/format'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useToast } from '@/components/ui/toast'
import type { DeviceAdminDetail } from '@/types/api'

/**
 * B11 — device access model, complex binding and the one-off sale price. All rules (B4 invariants) are
 * enforced server-side; the UI only offers the actions the admin's permissions allow and surfaces the 409s.
 *  - complex bind/unbind: complexes.manage · mode switch + sale price: devices.update
 */
export function DeviceOwnershipCard({ device }: { device: DeviceAdminDetail }) {
  const { hasPermission } = useAuth()
  const { toast } = useToast()
  const canBind = hasPermission(PERM.complexesManage)
  const canUpdate = hasPermission(PERM.devicesUpdate)
  const mode = device.ownership_mode ?? 'private'
  const complexId = device.complex_id ?? null

  const { data: complexes } = useComplexList()
  const binding = useComplexDeviceBinding()
  const changeMode = useChangeOwnershipMode(device.id)
  const update = useUpdateDevice(device.id)
  const [pickComplex, setPickComplex] = useState('')
  const [price, setPrice] = useState('')

  useEffect(() => {
    setPrice(device.sale_price_minor != null ? (device.sale_price_minor / 100).toFixed(2) : '')
  }, [device.sale_price_minor])

  const fail = (e: unknown) => toast({ variant: 'destructive', title: 'Əməliyyat rədd edildi', description: e instanceof ApiError ? e.message : undefined })
  const complexName = complexes?.find((c) => c.id === complexId)?.name

  const bindTo = (id: number) =>
    binding.mutate({ complexId: id, deviceId: device.id, bind: true }, { onSuccess: () => { toast({ variant: 'success', title: 'Cihaz kompleksə bağlandı' }); setPickComplex('') }, onError: fail })

  const savePrice = () => {
    const trimmed = price.trim().replace(',', '.')
    const value = trimmed === '' ? null : Math.round(Number(trimmed) * 100)
    if (value !== null && (!Number.isFinite(value) || value < 0)) {
      toast({ variant: 'destructive', title: 'Qiymət düzgün deyil' })
      return
    }
    update.mutate({ sale_price_minor: value }, { onSuccess: () => toast({ variant: 'success', title: 'Satış qiyməti qeyd edildi' }), onError: fail })
  }

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Rejim, kompleks və satış</CardTitle>
      </CardHeader>
      <CardContent className="grid gap-6 md:grid-cols-3">
        <div className="space-y-2">
          <p className="text-xs uppercase tracking-wide text-muted-foreground">Kompleks</p>
          {complexId ? (
            <div className="flex items-center gap-2 text-sm">
              <Link to={`/complexes/${complexId}`} className="font-medium hover:underline">{complexName ?? `#${complexId}`}</Link>
              {canBind && (
                <Button size="sm" variant="outline" disabled={binding.isPending}
                  onClick={() => binding.mutate({ complexId, deviceId: device.id, bind: false }, { onSuccess: () => toast({ variant: 'success', title: 'Cihaz kompleksdən ayrıldı' }), onError: fail })}>
                  Ayır
                </Button>
              )}
            </div>
          ) : canBind ? (
            <BindPicker complexes={complexes ?? []} value={pickComplex} onChange={setPickComplex} pending={binding.isPending} onBind={bindTo} />
          ) : (
            <p className="text-sm text-muted-foreground">Bağlı deyil</p>
          )}
        </div>

        <div className="space-y-2">
          <p className="text-xs uppercase tracking-wide text-muted-foreground">Giriş rejimi</p>
          <div className="flex items-center gap-2">
            {mode === 'complex' ? <Badge variant="secondary">Kompleks (ortaq)</Badge> : <Badge variant="muted">Şəxsi</Badge>}
            {canUpdate && (
              <Button size="sm" variant="outline" disabled={changeMode.isPending}
                onClick={() => changeMode.mutate(mode === 'complex' ? 'private' : 'complex', { onSuccess: () => toast({ variant: 'success', title: 'Rejim dəyişdirildi' }), onError: fail })}>
                {changeMode.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                {mode === 'complex' ? 'Şəxsi rejimə keçir' : 'Kompleks rejiminə keçir'}
              </Button>
            )}
          </div>
          <p className="text-xs text-muted-foreground">
            {mode === 'complex'
              ? 'Hər sakinin öz abunəliyi var; sahib yoxdur.'
              : 'Kompleks rejimi üçün cihaz kompleksə bağlı olmalı, sahibi və aktiv istifadəçisi olmamalıdır.'}
          </p>
        </div>

        <div className="space-y-2">
          <Label htmlFor="sale-price" className="text-xs uppercase tracking-wide text-muted-foreground">Cihaz satış qiyməti (birdəfəlik)</Label>
          {canUpdate ? (
            <div className="flex items-center gap-2">
              <Input id="sale-price" inputMode="decimal" placeholder="məs. 250.00" value={price} onChange={(e) => setPrice(e.target.value)} className="max-w-[140px]" />
              <span className="text-sm text-muted-foreground">AZN</span>
              <Button size="sm" onClick={savePrice} disabled={update.isPending}>Saxla</Button>
            </div>
          ) : (
            <p className="text-sm">{device.sale_price_minor != null ? formatMoney(device.sale_price_minor) : '—'}</p>
          )}
          <p className="text-xs text-muted-foreground">
            Yalnız qeyd: ödəniş app xaricində aparılır, abunəlik qiymətinə təsir etmir.
            {device.sale_recorded_at && <> Son qeyd: {formatDateTime(device.sale_recorded_at)}</>}
          </p>
        </div>
      </CardContent>
    </Card>
  )
}

function BindPicker({ complexes, value, onChange, pending, onBind }: {
  complexes: { id: number; name: string }[]
  value: string
  onChange: (v: string) => void
  pending: boolean
  onBind: (id: number) => void
}) {
  return (
    <div className="flex items-center gap-2">
      <select aria-label="Kompleks seç" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={value} onChange={(e) => onChange(e.target.value)}>
        <option value="">Kompleks seç…</option>
        {complexes.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
      </select>
      <Button size="sm" disabled={!value || pending} onClick={() => onBind(Number(value))}>Bağla</Button>
    </div>
  )
}
