import { type ReactNode, useRef, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { CheckCircle2, Download, History, Loader2, LogOut, PlugZap, Send, Upload, XCircle } from 'lucide-react'
import {
  type SettingField,
  type SettingGroup,
  type SystemHealth,
  exportSettings,
  usePaymentsDiagnostics,
  usePaymentsTestCreate,
  useCompareVersions,
  useForceLogout,
  useImportSettings,
  useRestoreVersion,
  useSettings,
  useSettingsVersions,
  useSystemHealth,
  useTestEmail,
  useTestEmailOtp,
  useTestSetting,
  useTestSms,
  useTraccarStatus,
  useUpdateGroup,
} from '@/api/settings'
import { ApiError } from '@/lib/api'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import { cn } from '@/lib/utils'

const GROUP_LABELS: Record<string, string> = {
  general: 'Ümumi',
  security: 'Təhlükəsizlik',
  email: 'E-poçt',
  otp: 'OTP',
  payments: 'Ödənişlər',
  traccar: 'Traccar',
  sms: 'SMS',
  subscriptions: 'Abunəliklər',
}
const TESTABLE = new Set(['traccar', 'email', 'payments'])

export function SettingsPage({ defaultTab = 'general' }: { defaultTab?: string }) {
  const [params, setParams] = useSearchParams()
  const { data, isLoading, isError, error, refetch } = useSettings()
  const [showHistory, setShowHistory] = useState(false)
  const tab = params.get('tab') ?? defaultTab

  const setTab = (t: string) => setParams((p) => {
    p.set('tab', t)
    return p
  }, { replace: true })

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Parametrlər</h1>
          <p className="text-sm text-muted-foreground">Bütün sistem konfiqurasiyası — bazadan idarə olunur, dəyişiklik dərhal qüvvəyə minir.</p>
        </div>
        <SettingsToolbar showHistory={showHistory} onToggleHistory={() => setShowHistory((v) => !v)} />
      </div>

      {showHistory && <VersionHistory />}

      {isLoading ? (
        <LoadingState />
      ) : isError ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : !data ? null : (
        <div className="space-y-4">
          <div className="flex flex-wrap gap-1 rounded-md bg-muted p-1">
            {data.groups.map((g) => (
              <button
                key={g.group}
                type="button"
                onClick={() => setTab(g.group)}
                className={cn(
                  'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                  tab === g.group ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
                )}
              >
                {GROUP_LABELS[g.group] ?? g.group}
              </button>
            ))}
            <button
              type="button"
              onClick={() => setTab('system')}
              className={cn(
                'rounded-sm px-3 py-1.5 text-sm font-medium transition-colors',
                tab === 'system' ? 'bg-card text-foreground shadow-sm' : 'text-muted-foreground hover:text-foreground',
              )}
            >
              Sistem
            </button>
          </div>

          {tab === 'system' ? (
            <SystemTab />
          ) : (
            <GroupForm
              key={tab}
              group={data.groups.find((g) => g.group === tab) ?? data.groups[0]}
              readonly={data.readonly[tab] ?? {}}
            />
          )}
        </div>
      )}
    </div>
  )
}

function GroupForm({ group, readonly }: { group: SettingGroup; readonly: Record<string, string> }) {
  const { toast } = useToast()
  const update = useUpdateGroup()
  const test = useTestSetting(group.group)
  const [form, setForm] = useState<Record<string, unknown>>(() => ({ ...group.values }))

  const set = (k: string, v: unknown) => setForm((f) => ({ ...f, [k]: v }))

  const save = () =>
    update.mutate(
      { group: group.group, values: form },
      {
        onSuccess: () => toast({ variant: 'success', title: 'Yadda saxlanıldı', description: GROUP_LABELS[group.group] }),
        onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
      },
    )

  const runTest = () =>
    test.mutate(undefined, {
      onSuccess: (r) =>
        toast({ variant: r.ok ? 'success' : 'destructive', title: r.ok ? 'Bağlantı uğurlu' : 'Bağlantı uğursuz', description: r.message }),
      onError: (err) => toast({ variant: 'destructive', title: 'Test xətası', description: err instanceof ApiError ? err.message : undefined }),
    })

  return (
    <Card className="max-w-3xl">
      <CardHeader>
        <CardTitle className="text-base">{GROUP_LABELS[group.group] ?? group.group}</CardTitle>
      </CardHeader>
      <CardContent className="space-y-5">
        <div className="grid gap-5 sm:grid-cols-2">
          {group.fields.map((f) => (
            <FieldInput
              key={f.key}
              field={f}
              value={form[f.key]}
              secretSet={group.secrets_set[f.key]}
              onChange={(v) => set(f.key, v)}
            />
          ))}
        </div>

        {Object.keys(readonly).length > 0 && (
          <div className="grid gap-5 border-t pt-4 sm:grid-cols-2">
            {Object.entries(readonly).map(([k, v]) => (
              <div key={k} className="space-y-1.5">
                <Label className="text-xs text-muted-foreground">{k === 'webhook_url' ? 'Webhook URL' : k === 'callback_url' ? 'Callback URL' : k} (yalnız oxu)</Label>
                <Input value={v} readOnly className="bg-muted font-mono text-xs" />
              </div>
            ))}
          </div>
        )}

        <div className="flex items-center gap-3 border-t pt-4">
          <Button onClick={save} disabled={update.isPending}>
            {update.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
            Yadda saxla
          </Button>
          {TESTABLE.has(group.group) && (
            <Button variant="outline" onClick={runTest} disabled={test.isPending}>
              {test.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <PlugZap className="mr-2 h-4 w-4" />}
              Bağlantını yoxla
            </Button>
          )}
        </div>

        <GroupActions group={group.group} />
      </CardContent>
    </Card>
  )
}

/** Group-specific operational actions (real backend calls — no mock). */
function GroupActions({ group }: { group: string }) {
  if (group === 'email') return <EmailActions />
  if (group === 'sms') return <SmsActions />
  if (group === 'security') return <SecurityActions />
  if (group === 'traccar') return <TraccarStatusPanel />
  if (group === 'payments') return <PaymentsStatus />
  return null
}

function Indicator({ ok, label, value }: { ok: boolean | null; label: string; value?: string }) {
  return (
    <div className="flex items-center justify-between gap-2 rounded-md border px-3 py-2 text-sm">
      <span className="flex items-center gap-2">
        {ok === null ? <span className="h-2 w-2 rounded-full bg-muted-foreground/40" /> : ok ? <CheckCircle2 className="h-4 w-4 text-emerald-600" /> : <XCircle className="h-4 w-4 text-destructive" />}
        {label}
      </span>
      {value !== undefined && <span className="font-medium tabular-nums">{value}</span>}
    </div>
  )
}

function PaymentsStatus() {
  const { toast } = useToast()
  const diag = usePaymentsDiagnostics()
  const create = usePaymentsTestCreate()
  const d = diag.data

  const check = () => diag.mutate(undefined, {
    onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
  })
  const testCreate = () => {
    if (!window.confirm('Test ödəniş yaradılsın? Kart daxil edilmir, heç nə tutulmur (sandbox, avto-bitir) — yalnız confirmUrl yoxlanır.')) return
    create.mutate(undefined, {
      onSuccess: (r) => toast({ variant: r.ok ? 'success' : 'destructive', title: r.ok ? 'confirmUrl alındı' : 'Uğursuz', description: r.confirm_url ?? r.message }),
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <div className="space-y-3 border-t pt-4">
      <div className="flex flex-wrap items-center gap-2">
        <p className="text-sm font-medium">Kapital / BirPay vəziyyəti</p>
        <Button variant="outline" size="sm" onClick={check} disabled={diag.isPending}>
          {diag.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <PlugZap className="mr-2 h-4 w-4" />}Vəziyyəti yoxla
        </Button>
        <Button variant="outline" size="sm" onClick={testCreate} disabled={create.isPending || !d?.ok}>
          {create.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}Test ödəniş (confirmUrl)
        </Button>
      </div>

      {d && (
        <div className="grid gap-2 sm:grid-cols-2">
          <Indicator ok={d.oauth.ok} label="OAuth token" value={d.oauth.ok ? `${d.oauth.expires_in}s` : d.oauth.message} />
          <Indicator ok={d.authenticated.ok} label="Autentifikasiya" value={d.authenticated.message} />
          <Indicator ok={d.ok} label="Bağlantı" value={d.ok ? 'OK' : '—'} />
          <Indicator ok={d.merchant_id !== ''} label="Merchant" value={d.merchant_id || '—'} />
          <Indicator ok={null} label="Terminal" value={d.terminal_id || '—'} />
          <Indicator ok={null} label="Rejim" value={d.mode} />
          <Indicator ok={null} label="API host" value={d.host.replace('https://', '')} />
        </div>
      )}
      {create.data?.confirm_url && (
        <div className="space-y-1.5">
          <Label className="text-xs text-muted-foreground">Son test confirmUrl (yalnız oxu — açmayın)</Label>
          <Input value={create.data.confirm_url} readOnly className="bg-muted font-mono text-xs" />
        </div>
      )}
    </div>
  )
}

function EmailActions() {
  const { toast } = useToast()
  const [to, setTo] = useState('')
  const [template, setTemplate] = useState('login')
  const testEmail = useTestEmail()
  const testOtp = useTestEmailOtp()
  const notify = (r: { ok: boolean; message: string }) =>
    toast({ variant: r.ok ? 'success' : 'destructive', title: r.ok ? 'Uğurlu' : 'Uğursuz', description: r.message })

  return (
    <div className="space-y-3 border-t pt-4">
      <p className="text-sm font-medium">Test göndərmələri</p>
      <div className="flex flex-wrap items-end gap-2">
        <div className="grow space-y-1.5"><Label className="text-xs">Qəbuledici e-poçt</Label>
          <Input type="email" placeholder="ad@nümunə.az" value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
        <Select value={template} onChange={(e) => setTemplate(e.target.value)} className="w-44">
          <option value="registration">Qeydiyyat OTP</option>
          <option value="login">Giriş OTP</option>
          <option value="password_reset">Parol bərpa OTP</option>
        </Select>
      </div>
      <div className="flex flex-wrap gap-2">
        <Button variant="outline" disabled={!to || testEmail.isPending} onClick={() => testEmail.mutate(to, { onSuccess: notify })}>
          {testEmail.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}Test e-poçtu göndər
        </Button>
        <Button variant="outline" disabled={!to || testOtp.isPending} onClick={() => testOtp.mutate({ to, template }, { onSuccess: notify })}>
          {testOtp.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}Test OTP göndər
        </Button>
      </div>
    </div>
  )
}

function SmsActions() {
  const { toast } = useToast()
  const [to, setTo] = useState('')
  const testSms = useTestSms()
  return (
    <div className="space-y-3 border-t pt-4">
      <p className="text-sm font-medium">Test SMS</p>
      <p className="text-xs text-muted-foreground">SMS yalnız ehtiyat (fallback) kanaldır.</p>
      <div className="flex flex-wrap items-end gap-2">
        <div className="grow space-y-1.5"><Label className="text-xs">Nömrə</Label>
          <Input placeholder="+994..." value={to} onChange={(e) => setTo(e.target.value)} />
        </div>
        <Button variant="outline" disabled={!to || testSms.isPending} onClick={() =>
          testSms.mutate(to, { onSuccess: (r) => toast({ variant: r.ok ? 'success' : 'destructive', title: r.ok ? 'Uğurlu' : 'Məlumat', description: r.message }) })}>
          {testSms.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Send className="mr-2 h-4 w-4" />}Test SMS
        </Button>
      </div>
    </div>
  )
}

function SecurityActions() {
  const { toast } = useToast()
  const forceLogout = useForceLogout()
  const run = () => {
    if (!window.confirm('Bütün sessiyaları məcburi bağla? Bütün adminlər və istifadəçilər yenidən giriş etməli olacaq (siz daxil).')) return
    forceLogout.mutate(undefined, {
      onSuccess: (r: { user_tokens_revoked?: number }) => toast({ variant: 'success', title: 'Bütün sessiyalar bağlandı', description: `${r.user_tokens_revoked ?? 0} istifadəçi tokeni ləğv edildi` }),
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }
  return (
    <div className="space-y-2 border-t pt-4">
      <p className="text-sm font-medium">Sessiya idarəetməsi</p>
      <Button variant="destructive" onClick={run} disabled={forceLogout.isPending}>
        {forceLogout.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <LogOut className="mr-2 h-4 w-4" />}
        Bütün sessiyaları məcburi bağla
      </Button>
    </div>
  )
}

function TraccarStatusPanel() {
  const { data, isLoading } = useTraccarStatus(true)
  const online = data?.connection === 'connected'
  const fmt = (v: string | null | undefined) => (v ? new Date(v).toLocaleString('az') : '—')
  return (
    <div className="space-y-2 border-t pt-4">
      <div className="flex items-center gap-2">
        <p className="text-sm font-medium">Traccar statusu</p>
        {isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <Badge variant={online ? 'success' : 'destructive'}>{online ? 'Connected' : 'Offline'}</Badge>}
      </div>
      {data && (
        <div className="grid gap-1 text-sm sm:grid-cols-2">
          <Row k="Versiya" v={data.version ?? '—'} />
          <Row k="Cihaz sayı" v={String(data.device_count)} />
          <Row k="Növbə" v={String(data.queue_size)} />
          <Row k="Son webhook" v={fmt(data.last_webhook)} />
          <Row k="Son əmr" v={fmt(data.last_command)} />
          <Row k="Son ACK" v={fmt(data.last_ack)} />
          <Row k="Son cihaz sync" v={fmt(data.last_device_sync)} />
        </div>
      )}
    </div>
  )
}

function FieldInput({
  field,
  value,
  secretSet,
  onChange,
}: {
  field: SettingField
  value: unknown
  secretSet?: boolean
  onChange: (v: unknown) => void
}) {
  const id = `set-${field.key}`

  if (field.type === 'bool') {
    const on = value === true || value === 1 || value === '1'
    return (
      <div className="flex items-center justify-between gap-3 sm:col-span-2">
        <Label htmlFor={id} className="text-sm font-medium">{field.label}</Label>
        <button
          id={id}
          type="button"
          role="switch"
          aria-checked={on}
          onClick={() => onChange(!on)}
          className={cn('relative inline-flex h-6 w-11 items-center rounded-full transition-colors', on ? 'bg-primary' : 'bg-input')}
        >
          <span className={cn('inline-block h-5 w-5 transform rounded-full bg-white shadow transition-transform', on ? 'translate-x-5' : 'translate-x-0.5')} />
        </button>
      </div>
    )
  }

  return (
    <div className="space-y-1.5">
      <Label htmlFor={id} className="text-sm font-medium">{field.label}</Label>
      {field.type === 'select' ? (
        <Select id={id} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}>
          {(field.options ?? []).map((o) => (
            <option key={o} value={o}>{o === '' ? '— yoxdur —' : o}</option>
          ))}
        </Select>
      ) : field.type === 'text' ? (
        <Textarea id={id} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)} rows={2} />
      ) : field.type === 'secret' ? (
        <Input
          id={id}
          type="password"
          autoComplete="new-password"
          placeholder={secretSet ? '•••••• (dəyişmək üçün doldurun)' : 'Təyin edilməyib'}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.target.value)}
        />
      ) : (
        <Input
          id={id}
          type={field.type === 'int' ? 'number' : 'text'}
          value={String(value ?? '')}
          onChange={(e) => onChange(e.target.value)}
        />
      )}
    </div>
  )
}

function SystemTab() {
  const { data, isLoading, isError, error, refetch } = useSystemHealth()
  if (isLoading) return <LoadingState />
  if (isError) return <ErrorState error={error} onRetry={() => refetch()} />
  if (!data) return null
  return <HealthCards h={data} />
}

function HealthCards({ h }: { h: SystemHealth }) {
  const ok = (b: boolean) => <Badge variant={b ? 'success' : 'destructive'}>{b ? 'OK' : 'Xəta'}</Badge>
  const traccarOnline = h.traccar.status === 'connected'
  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      <HCard title="Tətbiq"><Row k="Laravel" v={h.app.laravel} /><Row k="PHP" v={h.app.php} /><Row k="Mühit" v={h.app.env} /></HCard>
      <HCard title="Verilənlər bazası" badge={ok(h.database.ok)}><Row k="Bağlantı" v={h.database.ok ? 'Aktiv' : 'Yoxdur'} /></HCard>
      <HCard title="Redis" badge={ok(h.redis.ok)}><Row k="Bağlantı" v={h.redis.ok ? 'Aktiv' : 'Yoxdur'} /></HCard>
      <HCard title="Növbə (Queue)"><Row k="Driver" v={h.queue.connection} /><Row k="Uğursuz işlər" v={String(h.queue.failed)} /><Row k="Horizon" v={h.horizon.installed ? 'Quraşdırılıb' : '—'} /></HCard>
      <HCard title="Disk"><Row k="İstifadə" v={`${h.disk.used_percent}%`} /><Row k="Boş" v={`${h.disk.free_gb} GB`} /><Row k="Ümumi" v={`${h.disk.total_gb} GB`} /></HCard>
      <HCard title="Resurslar"><Row k="PHP yaddaş" v={`${h.memory.php_used_mb} MB`} /><Row k="Limit" v={h.memory.php_limit} /><Row k="CPU yük (1d)" v={h.cpu.load_1m === null ? '—' : String(h.cpu.load_1m)} /></HCard>
      <HCard title="Traccar" badge={ok(traccarOnline)}>
        <Row k="Status" v={traccarOnline ? 'Connected' : 'Offline'} />
        <Row k="Versiya" v={h.traccar.version ?? '—'} />
        <Row k="Cihaz sayı" v={String(h.traccar.device_count)} />
        <Row k="Növbə" v={String(h.traccar.queue_size)} />
        <Row k="Son webhook" v={h.traccar.last_webhook ? new Date(h.traccar.last_webhook).toLocaleString('az') : '—'} />
      </HCard>
      <HCard title="SMTP" badge={ok(h.smtp.configured)}><Row k="Konfiqurasiya" v={h.smtp.configured ? 'Var' : 'Yox'} /></HCard>
      <HCard title="Ödəniş" badge={<Badge variant={h.payment.enabled ? 'success' : 'muted'}>{h.payment.enabled ? 'Aktiv' : 'Söndürülüb'}</Badge>}><Row k="Konfiqurasiya" v={h.payment.configured ? 'Var' : 'Yox'} /></HCard>
      <HCard title="Cloudflare" badge={<Badge variant={h.cloudflare.proxied ? 'success' : 'muted'}>{h.cloudflare.proxied ? 'Proxy' : 'Birbaşa'}</Badge>}><Row k="CF-RAY" v={h.cloudflare.ray ?? '—'} /></HCard>
    </div>
  )
}

function HCard({ title, badge, children }: { title: string; badge?: ReactNode; children: ReactNode }) {
  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-sm">{title}</CardTitle>
        {badge}
      </CardHeader>
      <CardContent className="space-y-1 text-sm">{children}</CardContent>
    </Card>
  )
}

function Row({ k, v }: { k: string; v: string }) {
  return (
    <div className="flex items-center justify-between gap-2">
      <span className="text-muted-foreground">{k}</span>
      <span className="font-medium tabular-nums">{v}</span>
    </div>
  )
}

function SettingsToolbar({ showHistory, onToggleHistory }: { showHistory: boolean; onToggleHistory: () => void }) {
  const { toast } = useToast()
  const fileRef = useRef<HTMLInputElement>(null)
  const importMut = useImportSettings()
  const [exporting, setExporting] = useState(false)

  const onExport = async () => {
    setExporting(true)
    try {
      const data = await exportSettings()
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      const url = URL.createObjectURL(blob)
      const a = document.createElement('a')
      a.href = url
      a.download = `salam-settings-${new Date().toISOString().slice(0, 10)}.json`
      a.click()
      URL.revokeObjectURL(url)
    } catch (err) {
      toast({ variant: 'destructive', title: 'Eksport xətası', description: err instanceof ApiError ? err.message : undefined })
    } finally {
      setExporting(false)
    }
  }

  const onImportFile = async (file: File) => {
    try {
      const parsed = JSON.parse(await file.text())
      const settings = (parsed.settings ?? parsed) as Record<string, unknown>
      if (!settings || typeof settings !== 'object') throw new Error('Yanlış format')
      if (!window.confirm('Bu fayldakı parametrləri import edib mövcudları əvəz etmək istəyirsiniz?')) return
      importMut.mutate(settings, {
        onSuccess: (r: { applied?: number }) => toast({ variant: 'success', title: 'Import edildi', description: `${r.applied ?? 0} parametr tətbiq olundu` }),
        onError: (err) => toast({ variant: 'destructive', title: 'Import xətası', description: err instanceof ApiError ? err.message : undefined }),
      })
    } catch {
      toast({ variant: 'destructive', title: 'Import xətası', description: 'JSON oxunmadı' })
    }
  }

  return (
    <div className="flex flex-wrap gap-2">
      <input ref={fileRef} type="file" accept="application/json,.json" className="hidden"
        onChange={(e) => { const f = e.target.files?.[0]; if (f) onImportFile(f); e.target.value = '' }} />
      <Button variant="outline" size="sm" onClick={onExport} disabled={exporting}>
        {exporting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}Eksport
      </Button>
      <Button variant="outline" size="sm" onClick={() => fileRef.current?.click()} disabled={importMut.isPending}>
        {importMut.isPending ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Upload className="mr-2 h-4 w-4" />}Import
      </Button>
      <Button variant={showHistory ? 'default' : 'outline'} size="sm" onClick={onToggleHistory}>
        <History className="mr-2 h-4 w-4" />Tarixçə
      </Button>
    </div>
  )
}

function VersionHistory() {
  const { toast } = useToast()
  const { data: versions, isLoading } = useSettingsVersions(true)
  const compare = useCompareVersions()
  const restore = useRestoreVersion()
  const [from, setFrom] = useState<number | ''>('')
  const [to, setTo] = useState<number | ''>('')

  const doRestore = (id: number) => {
    if (!window.confirm(`#${id} versiyasına qaytarılsın? Cari parametrlər əvəz olunacaq (yeni versiya yaranacaq).`)) return
    restore.mutate(id, {
      onSuccess: () => toast({ variant: 'success', title: 'Bərpa edildi', description: `#${id} versiyası tətbiq olundu` }),
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Card>
      <CardHeader><CardTitle className="text-base">Versiya tarixçəsi</CardTitle></CardHeader>
      <CardContent className="space-y-4">
        <div className="flex flex-wrap items-end gap-2">
          <div className="space-y-1"><Label className="text-xs">Müqayisə: A</Label>
            <Select value={String(from)} onChange={(e) => setFrom(e.target.value ? Number(e.target.value) : '')} className="w-28">
              <option value="">—</option>
              {versions?.map((v) => <option key={v.id} value={v.id}>#{v.id}</option>)}
            </Select>
          </div>
          <div className="space-y-1"><Label className="text-xs">B</Label>
            <Select value={String(to)} onChange={(e) => setTo(e.target.value ? Number(e.target.value) : '')} className="w-28">
              <option value="">—</option>
              {versions?.map((v) => <option key={v.id} value={v.id}>#{v.id}</option>)}
            </Select>
          </div>
          <Button variant="outline" size="sm" disabled={from === '' || to === '' || compare.isPending}
            onClick={() => compare.mutate({ from: Number(from), to: Number(to) })}>Müqayisə et</Button>
        </div>

        {compare.data && (
          <div className="rounded-md border p-3 text-sm">
            <p className="mb-2 font-medium">#{compare.data.from.id} → #{compare.data.to.id} ({compare.data.diff.length} fərq)</p>
            {compare.data.diff.length === 0 ? <p className="text-muted-foreground">Fərq yoxdur.</p> : (
              <div className="space-y-1">
                {compare.data.diff.map((d) => (
                  <div key={d.key} className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 font-mono text-xs">
                    <span className="truncate">{d.key}</span>
                    <span className="text-muted-foreground">{String(d.from ?? '∅')} → {String(d.to ?? '∅')}</span>
                    <span />
                  </div>
                ))}
              </div>
            )}
          </div>
        )}

        {isLoading ? <LoadingState /> : (
          <div className="divide-y">
            {versions?.length ? versions.map((v) => (
              <div key={v.id} className="flex items-center justify-between gap-3 py-2 text-sm">
                <div className="min-w-0">
                  <span className="font-medium">#{v.id}</span>
                  <Badge variant="muted" className="ml-2">{v.reason}</Badge>
                  {v.scope_group && <span className="ml-2 text-muted-foreground">{v.scope_group}</span>}
                  <div className="text-xs text-muted-foreground">{v.actor_label ?? '—'} · {v.ip ?? '—'} · {v.created_at ? new Date(v.created_at).toLocaleString('az') : '—'}</div>
                </div>
                <Button variant="ghost" size="sm" onClick={() => doRestore(v.id)} disabled={restore.isPending}>Bərpa et</Button>
              </div>
            )) : <p className="py-3 text-sm text-muted-foreground">Hələ versiya yoxdur.</p>}
          </div>
        )}
      </CardContent>
    </Card>
  )
}
