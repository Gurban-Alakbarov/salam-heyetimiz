import { type ReactNode, useState } from 'react'
import { GeoMapPicker } from '@/components/GeoMap'
import { Link, useNavigate } from 'react-router-dom'
import { Check, ChevronLeft, Loader2, LockOpen, Radio } from 'lucide-react'
import { useComplexDeviceBinding, useComplexList } from '@/api/complexes'
import {
  useAssignDevice, useChangeOwnershipMode, useCreateDevice, useDeviceCommands, useDeviceLookups, usePingDevice,
  useRelayTest, useUpdateDevice, type PingResult,
} from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { useAuth } from '@/auth/useAuth'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { ImageUploadField } from '@/components/ImageUploadField'
import { StatusBadge } from '@/components/StatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { relativeTime } from '@/lib/format'
import type { DriverType } from '@/types/api'

const STEPS = ['Cihaz', 'Harada quraşdırılıb', 'Şəkil və məsafə', 'Yoxlama'] as const

/**
 * "Cihaz quraşdır" — the technician's whole install in one flow: register the barrier, put it in a complex (or give it
 * an owner), photo + distance limit, then check it is reachable and open it once. Every step uses the existing
 * endpoints and rules; steps 2–3 can be skipped and done later on the device page.
 */
export function InstallWizardPage() {
  const [step, setStep] = useState(0)
  const [deviceId, setDeviceId] = useState<number | null>(null)
  const [deviceName, setDeviceName] = useState('')

  return (
    <div className="mx-auto max-w-3xl space-y-6">
      <Link to="/devices" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ChevronLeft className="h-4 w-4" /> Cihazlar
      </Link>
      <h1 className="text-2xl font-semibold tracking-tight">Cihaz quraşdır</h1>

      <ol className="flex flex-wrap gap-2">
        {STEPS.map((label, i) => (
          <li key={label} className={`flex items-center gap-2 rounded-full border px-3 py-1 text-sm ${i === step ? 'border-primary bg-primary/10 font-medium text-primary' : i < step ? 'text-emerald-700' : 'text-muted-foreground'}`}>
            <span className="flex h-5 w-5 items-center justify-center rounded-full border text-xs">{i < step ? <Check className="h-3 w-3" /> : i + 1}</span>
            {label}
          </li>
        ))}
      </ol>

      {step === 0 && <StepDevice onDone={(id, name) => { setDeviceId(id); setDeviceName(name); setStep(1) }} />}
      {step === 1 && deviceId && <StepWhere deviceId={deviceId} onDone={() => setStep(2)} />}
      {step === 2 && deviceId && <StepPhoto deviceId={deviceId} onDone={() => setStep(3)} />}
      {step === 3 && deviceId && <StepTest deviceId={deviceId} name={deviceName} />}
    </div>
  )
}

function Field({ id, label, hint, error, children }: { id: string; label: string; hint?: string; error?: string; children: ReactNode }) {
  return (
    <div className="space-y-1.5">
      <Label htmlFor={id}>{label}</Label>
      {children}
      {error ? <p className="text-xs text-destructive">{error}</p> : hint && <p className="text-xs text-muted-foreground">{hint}</p>}
    </div>
  )
}

function StepDevice({ onDone }: { onDone: (id: number, name: string) => void }) {
  const lookups = useDeviceLookups()
  const create = useCreateDevice()
  const [f, setF] = useState({ serial: '', model: '', sim: '+994', name: '', address: '', lat: '', lng: '', driver: 'traccar' as DriverType })
  const set = (k: keyof typeof f) => (v: string) => setF((x) => ({ ...x, [k]: v }))
  const err = (k: string) => (create.error instanceof ApiError ? create.error.fieldError(k) : undefined)
  const ready = f.serial.trim().length >= 6 && f.model !== '' && /^\+994\d{9}$/.test(f.sim.replace(/\s/g, ''))

  const submit = () => create.mutate({
    serial: f.serial.trim(),
    device_model_id: Number(f.model),
    sim_phone: f.sim.replace(/\s/g, ''),
    driver_type: f.driver,
    location_label: f.name.trim() || null,
    address: f.address.trim() || null,
    latitude: f.lat ? Number(f.lat) : null,
    longitude: f.lng ? Number(f.lng) : null,
  }, { onSuccess: (d) => onDone(d.id, f.name.trim() || d.serial) })

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">1. Cihazın məlumatları</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Field id="w-serial" label="IMEI" hint="Cihazın üzərindəki 15 rəqəm" error={err('serial')}>
            <Input id="w-serial" inputMode="numeric" value={f.serial} onChange={(e) => set('serial')(e.target.value)} />
          </Field>
          <Field id="w-model" label="Cihaz modeli" error={err('device_model_id')}>
            <Select id="w-model" value={f.model} onChange={(e) => {
              const m = lookups.data?.device_models.find((x) => String(x.id) === e.target.value)
              setF((x) => ({ ...x, model: e.target.value, driver: (m?.default_driver_type as DriverType) ?? x.driver }))
            }}>
              <option value="">Model seçin…</option>
              {(lookups.data?.device_models ?? []).map((m) => <option key={m.id} value={m.id}>{m.vendor} {m.model_code}</option>)}
            </Select>
          </Field>
          <Field id="w-sim" label="SIM nömrəsi" hint="+994XXXXXXXXX" error={err('sim_phone')}>
            <Input id="w-sim" value={f.sim} onChange={(e) => set('sim')(e.target.value)} />
          </Field>
          <Field id="w-name" label="Cihazın adı" hint="Tətbiqdə görünəcək, məs. «Blok A şlaqbaum»" error={err('location_label')}>
            <Input id="w-name" value={f.name} onChange={(e) => set('name')(e.target.value)} />
          </Field>
          <Field id="w-addr" label="Ünvan" hint="Pin qoyulanda avtomatik dolur, düzəldə bilərsiniz" error={err('address')}>
            <Input id="w-addr" value={f.address} onChange={(e) => set('address')(e.target.value)} placeholder="Küçə, ev, rayon" />
          </Field>
        </div>
        <div className="space-y-1.5">
          <Label>Cihazın yeri</Label>
          <GeoMapPicker
            value={f.lat && f.lng ? { lat: Number(f.lat), lng: Number(f.lng) } : null}
            onChange={(p) => setF((x) => ({ ...x, lat: String(p.lat), lng: String(p.lng), address: p.address ?? x.address }))}
          />
          {(err('latitude') || err('longitude')) && <p className="text-xs text-destructive">{err('latitude') ?? err('longitude')}</p>}
          <p className="text-xs text-muted-foreground">«Yol göstər» və məsafə məhdudiyyəti (geofence) bu nöqtədən işləyir.</p>
        </div>
        {create.error && !(create.error instanceof ApiError && Object.keys(create.error.fields ?? {}).length > 0) && (
          <p className="text-sm text-destructive">{create.error instanceof ApiError ? create.error.message : 'Xəta'}</p>
        )}
        <div className="flex justify-end">
          <Button disabled={!ready || create.isPending} onClick={submit}>{create.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Cihazı yarat və davam et</Button>
        </div>
      </CardContent>
    </Card>
  )
}

function StepWhere({ deviceId, onDone }: { deviceId: number; onDone: () => void }) {
  const { hasPermission } = useAuth()
  const { toast } = useToast()
  const canBind = hasPermission(PERM.complexesManage) || hasPermission(PERM.complexesDevicesBind)
  const canAssign = hasPermission(PERM.devicesAssign)
  const [kind, setKind] = useState<'complex' | 'private'>(canBind ? 'complex' : 'private')
  const [complexId, setComplexId] = useState('')
  const [phone, setPhone] = useState('+994')
  const [email, setEmail] = useState('')
  const { data: complexes } = useComplexList()
  const binding = useComplexDeviceBinding()
  const mode = useChangeOwnershipMode(deviceId)
  const assign = useAssignDevice(deviceId)
  const fail = (e: unknown) => toast({ variant: 'destructive', title: 'Alınmadı', description: e instanceof ApiError ? e.message : undefined })
  const pending = binding.isPending || mode.isPending || assign.isPending

  const saveComplex = () => binding.mutate({ complexId: Number(complexId), deviceId, bind: true }, {
    onSuccess: () => mode.mutate('complex', {
      onSuccess: () => { toast({ variant: 'success', title: 'Cihaz kompleksin ortaq qapısı oldu' }); onDone() },
      onError: fail,
    }),
    onError: fail,
  })
  const saveOwner = () => assign.mutate({ owner_phone: phone.replace(/\s/g, ''), owner_email: email.trim() || undefined }, {
    onSuccess: () => { toast({ variant: 'success', title: 'Sahib təyin edildi' }); onDone() },
    onError: fail,
  })

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">2. Kimin qapısıdır?</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap gap-2">
          {canBind && <Button variant={kind === 'complex' ? 'default' : 'outline'} size="sm" onClick={() => setKind('complex')}>Yaşayış kompleksinin ortaq qapısı</Button>}
          {canAssign && <Button variant={kind === 'private' ? 'default' : 'outline'} size="sm" onClick={() => setKind('private')}>Bir şəxsin (fərdi) qapısı</Button>}
        </div>
        {kind === 'complex' && canBind ? (
          <Field id="w-cx" label="Kompleks" hint="Sakinlər öz abunəlikləri ilə açacaq; cihazın sahibi olmur">
            <Select id="w-cx" value={complexId} onChange={(e) => setComplexId(e.target.value)}>
              <option value="">Kompleks seçin…</option>
              {(complexes ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
            </Select>
          </Field>
        ) : canAssign ? (
          <div className="grid gap-4 sm:grid-cols-2">
            <Field id="w-phone" label="Sahibin telefonu"><Input id="w-phone" value={phone} onChange={(e) => setPhone(e.target.value)} /></Field>
            <Field id="w-email" label="Sahibin e-poçtu" hint="Tətbiqə bu e-poçtla daxil olacaq"><Input id="w-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} /></Field>
          </div>
        ) : <p className="text-sm text-muted-foreground">Bu addım üçün icazəniz yoxdur — atlayın, admin sonra təyin edəcək.</p>}
        <div className="flex justify-between">
          <Button variant="ghost" onClick={onDone} disabled={pending}>Sonra edərəm</Button>
          {kind === 'complex' && canBind ? (
            <Button disabled={!complexId || pending} onClick={saveComplex}>{pending && <Loader2 className="h-4 w-4 animate-spin" />} Kompleksə qoş və davam et</Button>
          ) : canAssign ? (
            <Button disabled={!/^\+994\d{9}$/.test(phone.replace(/\s/g, '')) || pending} onClick={saveOwner}>{pending && <Loader2 className="h-4 w-4 animate-spin" />} Sahibi təyin et və davam et</Button>
          ) : null}
        </div>
      </CardContent>
    </Card>
  )
}

function StepPhoto({ deviceId, onDone }: { deviceId: number; onDone: () => void }) {
  const update = useUpdateDevice(deviceId)
  const { toast } = useToast()
  const [geo, setGeo] = useState(false)
  const [radius, setRadius] = useState('100')
  const save = () => {
    if (!geo) return onDone()
    update.mutate({ geofence_enabled: true, geofence_radius_m: Number(radius) }, {
      onSuccess: () => { toast({ variant: 'success', title: 'Məsafə məhdudiyyəti açıldı' }); onDone() },
      onError: (e) => toast({ variant: 'destructive', title: 'Alınmadı', description: e instanceof ApiError ? e.message : undefined }),
    })
  }
  return (
    <Card>
      <CardHeader><CardTitle className="text-base">3. Şəkil və məsafə məhdudiyyəti</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <div className="space-y-1.5">
          <Label>Şlaqbaumun şəkli (tətbiqdə kartın fonu)</Label>
          <ImageUploadField deviceId={deviceId} currentUrl={null} />
        </div>
        <label className="flex items-start gap-2 text-sm">
          <input type="checkbox" className="mt-1" checked={geo} onChange={(e) => setGeo(e.target.checked)} />
          <span>Yalnız cihazın yaxınında açılsın (geofence)<span className="block text-xs text-muted-foreground">Bunun üçün 1-ci addımda koordinatlar yazılmalıdır.</span></span>
        </label>
        {geo && (
          <div className="max-w-[200px] space-y-1.5">
            <Label htmlFor="w-radius">Məsafə (metr)</Label>
            <Input id="w-radius" type="number" min={1} value={radius} onChange={(e) => setRadius(e.target.value)} />
          </div>
        )}
        <div className="flex justify-between">
          <Button variant="ghost" onClick={onDone}>Sonra edərəm</Button>
          <Button onClick={save} disabled={update.isPending}>{update.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Davam et</Button>
        </div>
      </CardContent>
    </Card>
  )
}

function StepTest({ deviceId, name }: { deviceId: number; name: string }) {
  const { hasPermission } = useAuth()
  const navigate = useNavigate()
  const ping = usePingDevice(deviceId)
  const relay = useRelayTest(deviceId)
  const commands = useDeviceCommands(hasPermission(PERM.commandsView) ? deviceId : 0, null)
  const [pingResult, setPingResult] = useState<PingResult | null>(null)
  const [confirm, setConfirm] = useState(false)
  const latest = commands.data?.data?.[0]

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">4. Yoxlama</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <p className="text-sm text-muted-foreground">Cihaz serverə qoşulubsa onlayn görünəcək. Sonra qapını bir dəfə sınaq üçün açın.</p>
        <div className="flex flex-wrap gap-2">
          {hasPermission(PERM.devicesDiagnostics) && (
            <Button variant="outline" disabled={ping.isPending} onClick={() => ping.mutate(undefined, { onSuccess: setPingResult })}>
              {ping.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Radio className="h-4 w-4" />} Qoşulmanı yoxla
            </Button>
          )}
          {hasPermission(PERM.commandsTest) && (
            <Button disabled={relay.isPending} onClick={() => setConfirm(true)}><LockOpen className="h-4 w-4" /> Qapını sınaq üçün aç</Button>
          )}
        </div>
        {pingResult && (
          <p className={`text-sm ${pingResult.online ? 'text-emerald-700' : 'text-destructive'}`}>
            {!pingResult.reachable ? 'Traccar serveri cavab vermədi.'
              : !pingResult.found ? 'Cihaz hələ Traccar-da görünmür — IMEI-ni və cihazın SMS ilə server ünvanına yönləndirildiyini yoxlayın.'
              : pingResult.online ? 'Cihaz onlayndır ✓' : `Cihaz oflayndır${pingResult.last_online_at ? ` (son siqnal ${relativeTime(pingResult.last_online_at)})` : ''} — elektrik, SIM və antenanı yoxlayın.`}
          </p>
        )}
        {latest && (
          <div className="flex items-center gap-2 text-sm">
            <span className="text-muted-foreground">Son əmr:</span>
            <StatusBadge kind="command" value={latest.state} />
            {latest.failure_reason && <span className="text-destructive">{latest.failure_reason}</span>}
          </div>
        )}
        <div className="flex justify-end">
          <Button onClick={() => navigate(`/devices/${deviceId}`)}>Bitir — cihaz səhifəsinə keç</Button>
        </div>
        <ConfirmDialog
          open={confirm}
          onOpenChange={setConfirm}
          title="Qapı həqiqətən açılsın?"
          description={`«${name}» qapısı dərhal açılacaq. Yerdə olduğunuza əmin olun.`}
          confirmLabel="Bəli, aç"
          loading={relay.isPending}
          onConfirm={() => { setConfirm(false); relay.mutate('open') }}
        />
      </CardContent>
    </Card>
  )
}
