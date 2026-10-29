import { type FormEvent, useEffect, useState } from 'react'
import { Loader2, Pencil, Plus } from 'lucide-react'
import { type CatalogDeviceModel, type CatalogKind, type CatalogOperator, type CatalogRegion, useCatalog, useSaveCatalogRow } from '@/api/catalog'
import { PageHeader } from '@/components/PageHeader'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'

const DRIVER_LABEL: Record<string, string> = { traccar: 'GPS (Traccar)', sms: 'SMS', ble: 'Bluetooth' }
const activeBadge = (on: boolean) => (on ? <Badge variant="success">Aktiv</Badge> : <Badge variant="muted">Söndürülüb</Badge>)

/**
 * Device catalog (phase 3): device models (open / close commands, pulse, user limit), regions and SIM operators —
 * until now only editable in the database. Rows are never deleted; switching one off hides it from the device form.
 */
export function CatalogPage() {
  const { data, isLoading, isError, error, refetch } = useCatalog()
  return (
    <div className="space-y-5">
      <PageHeader title="Kataloq" description="Cihaz modelləri, regionlar və SIM operatorları — cihaz formasındakı seçimlər buradan gəlir" />
      {isLoading ? <LoadingState /> : isError || !data ? <ErrorState error={error} onRetry={() => refetch()} /> : (
        <Tabs defaultValue="models">
          <TabsList>
            <TabsTrigger value="models">Cihaz modelləri ({data.device_models.length})</TabsTrigger>
            <TabsTrigger value="regions">Regionlar ({data.regions.length})</TabsTrigger>
            <TabsTrigger value="operators">SIM operatorları ({data.sim_operators.length})</TabsTrigger>
          </TabsList>
          <TabsContent value="models"><ModelsTab rows={data.device_models} /></TabsContent>
          <TabsContent value="regions"><SimpleTab kind="regions" rows={data.regions} regions={data.regions} /></TabsContent>
          <TabsContent value="operators"><SimpleTab kind="sim-operators" rows={data.sim_operators} regions={data.regions} /></TabsContent>
        </Tabs>
      )}
    </div>
  )
}

function ModelsTab({ rows }: { rows: CatalogDeviceModel[] }) {
  const [edit, setEdit] = useState<CatalogDeviceModel | 'new' | null>(null)
  return (
    <Card className="overflow-hidden">
      <div className="flex justify-end p-3"><Button size="sm" onClick={() => setEdit('new')}><Plus className="h-4 w-4" /> Yeni model</Button></div>
      <CardContent className="px-0 pb-0">
        {rows.length === 0 ? <EmptyState title="Model yoxdur" /> : (
          <Table>
            <TableHeader><TableRow><TableHead>Model</TableHead><TableHead>Bağlantı</TableHead><TableHead>Açma / bağlama əmri</TableHead><TableHead>İmpuls</TableHead><TableHead>İstifadəçi limiti</TableHead><TableHead>Cihaz</TableHead><TableHead>Status</TableHead><TableHead className="w-0" /></TableRow></TableHeader>
            <TableBody>
              {rows.map((m) => (
                <TableRow key={m.id}>
                  <TableCell className="font-medium">{m.vendor} {m.model_code}</TableCell>
                  <TableCell>{DRIVER_LABEL[m.default_driver_type ?? ''] ?? '—'}</TableCell>
                  <TableCell className="font-mono text-xs">{m.open_command ?? '—'} / {m.close_command ?? '—'}</TableCell>
                  <TableCell>{m.open_pulse_ms ? `${m.open_pulse_ms} ms` : '—'}</TableCell>
                  <TableCell>{m.whitelist_capacity}</TableCell>
                  <TableCell>{m.devices_count}</TableCell>
                  <TableCell>{activeBadge(m.is_active)}</TableCell>
                  <TableCell className="pr-4"><Button size="icon" variant="ghost" aria-label="Redaktə et" onClick={() => setEdit(m)}><Pencil className="h-4 w-4" /></Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
      <ModelDialog value={edit} onClose={() => setEdit(null)} />
    </Card>
  )
}

function ModelDialog({ value, onClose }: { value: CatalogDeviceModel | 'new' | null; onClose: () => void }) {
  const { toast } = useToast()
  const save = useSaveCatalogRow('device-models')
  const m = value && value !== 'new' ? value : null
  const empty = { vendor: '', model_code: '', default_driver_type: 'traccar', open_command: '', close_command: '', sms_open_command: '', open_pulse_ms: '', whitelist_capacity: '100', notes: '', is_active: true }
  const [f, setF] = useState(empty)
  useEffect(() => {
    if (!value) return
    setF(m ? {
      vendor: m.vendor, model_code: m.model_code, default_driver_type: m.default_driver_type ?? 'traccar',
      open_command: m.open_command ?? '', close_command: m.close_command ?? '', sms_open_command: m.sms_open_command ?? '',
      open_pulse_ms: m.open_pulse_ms ? String(m.open_pulse_ms) : '', whitelist_capacity: String(m.whitelist_capacity),
      notes: m.notes ?? '', is_active: m.is_active,
    } : empty)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [value])
  const set = (k: keyof typeof f) => (v: string | boolean) => setF((x) => ({ ...x, [k]: v }))
  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({
      id: m?.id,
      vendor: f.vendor.trim(), model_code: f.model_code.trim(), default_driver_type: f.default_driver_type,
      open_command: f.open_command.trim() || null, close_command: f.close_command.trim() || null,
      sms_open_command: f.sms_open_command.trim() || null,
      open_pulse_ms: f.open_pulse_ms ? Number(f.open_pulse_ms) : null,
      whitelist_capacity: Number(f.whitelist_capacity) || 100,
      notes: f.notes.trim() || null, is_active: f.is_active,
    }, {
      onSuccess: () => { toast({ variant: 'success', title: m ? 'Model yeniləndi' : 'Model yaradıldı' }); onClose() },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }
  return (
    <Dialog open={value !== null} onOpenChange={(o) => { if (!o && !save.isPending) onClose() }}>
      <DialogContent className="max-w-xl">
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{m ? `${m.vendor} ${m.model_code}` : 'Yeni cihaz modeli'}</DialogTitle>
            <DialogDescription>Cihazın qapını necə açdığını təyin edir.</DialogDescription>
          </DialogHeader>
          <div className="grid gap-3 py-4 sm:grid-cols-2">
            {m && m.devices_count > 0 && (
              <Alert className="sm:col-span-2"><AlertDescription>Bu model {m.devices_count} cihazda işləyir — əmr və impuls dəyişiklikləri o cihazlara dərhal təsir edir.</AlertDescription></Alert>
            )}
            <div className="space-y-1.5"><Label htmlFor="dm-v">İstehsalçı</Label><Input id="dm-v" value={f.vendor} onChange={(e) => set('vendor')(e.target.value)} required /></div>
            <div className="space-y-1.5"><Label htmlFor="dm-c">Model kodu</Label><Input id="dm-c" value={f.model_code} onChange={(e) => set('model_code')(e.target.value)} required /></div>
            <div className="space-y-1.5"><Label htmlFor="dm-d">Bağlantı növü</Label>
              <Select id="dm-d" value={f.default_driver_type} onChange={(e) => set('default_driver_type')(e.target.value)}>
                <option value="traccar">GPS (Traccar)</option><option value="sms">SMS</option><option value="ble">Bluetooth</option>
              </Select></div>
            <div className="space-y-1.5"><Label htmlFor="dm-w">İstifadəçi limiti (bir cihazda)</Label><Input id="dm-w" type="number" min={1} value={f.whitelist_capacity} onChange={(e) => set('whitelist_capacity')(e.target.value)} /></div>
            <div className="space-y-1.5"><Label htmlFor="dm-o">Açma əmri</Label><Input id="dm-o" className="font-mono" placeholder="RELAY,1#" value={f.open_command} onChange={(e) => set('open_command')(e.target.value)} /></div>
            <div className="space-y-1.5"><Label htmlFor="dm-cl">Bağlama əmri</Label><Input id="dm-cl" className="font-mono" placeholder="RELAY,0#" value={f.close_command} onChange={(e) => set('close_command')(e.target.value)} /></div>
            <div className="space-y-1.5"><Label htmlFor="dm-p">İmpuls müddəti (ms)</Label><Input id="dm-p" type="number" min={100} max={60000} value={f.open_pulse_ms} onChange={(e) => set('open_pulse_ms')(e.target.value)} />
              <p className="text-xs text-muted-foreground">Açıldıqdan neçə ms sonra avtomatik bağlansın</p></div>
            <div className="space-y-1.5"><Label htmlFor="dm-s">SMS ilə açma əmri</Label><Input id="dm-s" className="font-mono" value={f.sms_open_command} onChange={(e) => set('sms_open_command')(e.target.value)} /></div>
            <div className="space-y-1.5 sm:col-span-2"><Label htmlFor="dm-n">Qeyd</Label><Input id="dm-n" value={f.notes} onChange={(e) => set('notes')(e.target.value)} /></div>
            <label className="flex items-center gap-2 text-sm sm:col-span-2">
              <input type="checkbox" checked={f.is_active} onChange={(e) => set('is_active')(e.target.checked)} />
              Aktiv (cihaz formasında seçilə bilir)
            </label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={save.isPending}>İmtina</Button>
            <Button type="submit" disabled={save.isPending || !f.vendor.trim() || !f.model_code.trim()}>{save.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Yadda saxla</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** Regions and SIM operators share one table + dialog (code, name, active; regions: parent). */
function SimpleTab({ kind, rows, regions }: { kind: Extract<CatalogKind, 'regions' | 'sim-operators'>; rows: (CatalogRegion | CatalogOperator)[]; regions: CatalogRegion[] }) {
  const { toast } = useToast()
  const save = useSaveCatalogRow(kind)
  const [edit, setEdit] = useState<CatalogRegion | CatalogOperator | 'new' | null>(null)
  const row = edit && edit !== 'new' ? edit : null
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [parent, setParent] = useState('')
  const [active, setActive] = useState(true)
  useEffect(() => {
    if (!edit) return
    setCode(row?.code ?? ''); setName(row?.name ?? ''); setActive(row?.is_active ?? true)
    setParent(row && 'parent_id' in row && row.parent_id ? String(row.parent_id) : '')
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [edit])
  const isRegion = kind === 'regions'
  const submit = (e: FormEvent) => {
    e.preventDefault()
    save.mutate({ id: row?.id, code: code.trim(), name: name.trim(), is_active: active, ...(isRegion ? { parent_id: parent ? Number(parent) : null } : {}) }, {
      onSuccess: () => { toast({ variant: 'success', title: 'Yadda saxlanıldı' }); setEdit(null) },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }
  return (
    <Card className="overflow-hidden">
      <div className="flex justify-end p-3"><Button size="sm" onClick={() => setEdit('new')}><Plus className="h-4 w-4" /> {isRegion ? 'Yeni region' : 'Yeni operator'}</Button></div>
      <CardContent className="px-0 pb-0">
        {rows.length === 0 ? <EmptyState title="Qeyd yoxdur" /> : (
          <Table>
            <TableHeader><TableRow><TableHead>Ad</TableHead><TableHead>Kod</TableHead>{isRegion && <TableHead>Üst region</TableHead>}<TableHead>Status</TableHead><TableHead className="w-0" /></TableRow></TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.id}>
                  <TableCell className="font-medium">{r.name}</TableCell>
                  <TableCell className="font-mono text-xs">{r.code}</TableCell>
                  {isRegion && <TableCell>{'parent_id' in r && r.parent_id ? regions.find((x) => x.id === r.parent_id)?.name ?? '—' : '—'}</TableCell>}
                  <TableCell>{activeBadge(r.is_active)}</TableCell>
                  <TableCell className="pr-4"><Button size="icon" variant="ghost" aria-label="Redaktə et" onClick={() => setEdit(r)}><Pencil className="h-4 w-4" /></Button></TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
      <Dialog open={edit !== null} onOpenChange={(o) => { if (!o && !save.isPending) setEdit(null) }}>
        <DialogContent>
          <form onSubmit={submit}>
            <DialogHeader><DialogTitle>{row ? row.name : isRegion ? 'Yeni region' : 'Yeni SIM operatoru'}</DialogTitle></DialogHeader>
            <div className="space-y-3 py-4">
              <div className="space-y-1.5"><Label htmlFor="sc-n">Ad</Label><Input id="sc-n" value={name} onChange={(e) => setName(e.target.value)} required /></div>
              <div className="space-y-1.5"><Label htmlFor="sc-c">Kod</Label><Input id="sc-c" className="font-mono" value={code} onChange={(e) => setCode(e.target.value)} required /></div>
              {isRegion && (
                <div className="space-y-1.5"><Label htmlFor="sc-p">Üst region</Label>
                  <Select id="sc-p" value={parent} onChange={(e) => setParent(e.target.value)}>
                    <option value="">—</option>
                    {regions.filter((x) => x.id !== row?.id).map((x) => <option key={x.id} value={x.id}>{x.name}</option>)}
                  </Select></div>
              )}
              <label className="flex items-center gap-2 text-sm"><input type="checkbox" checked={active} onChange={(e) => setActive(e.target.checked)} /> Aktiv</label>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => setEdit(null)} disabled={save.isPending}>İmtina</Button>
              <Button type="submit" disabled={save.isPending || !name.trim() || !code.trim()}>{save.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Yadda saxla</Button>
            </DialogFooter>
          </form>
        </DialogContent>
      </Dialog>
    </Card>
  )
}
