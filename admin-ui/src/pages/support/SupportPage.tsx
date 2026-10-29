import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { CheckCircle2, Loader2, Radio, Search, XCircle } from 'lucide-react'
import { type Resident, useResidents } from '@/api/residents'
import { useDeviceCommands, usePingDevice, type PingResult } from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { useAuth } from '@/auth/useAuth'
import { PageHeader } from '@/components/PageHeader'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { formatDateTime, relativeTime } from '@/lib/format'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

/**
 * "Qapı açılmır" — the support call in one screen. Find the person by phone / email / name; for every gate they use,
 * the card lists what has to be true for the gate to open (device active, online, subscription active) and their
 * last open attempts with the reason, plus a live "is the barrier reachable" check.
 */
export function SupportPage() {
  const [params, setParams] = useSearchParams()
  const [q, setQ] = useState(params.get('q') ?? '')
  const term = useDebouncedValue(q.trim(), 350)
  const { data, isLoading, isError, error, refetch } = useResidents({ q: term || undefined, limit: 20 })
  const rows = term.length >= 2 ? data?.data ?? [] : []

  return (
    <div className="space-y-5">
      <PageHeader title="Dəstək: qapı açılmır" description="Sakini tapın — hər cihaz üçün qapının niyə açılmadığı bir baxışda görünür" />
      <div className="relative max-w-md">
        <Search className="pointer-events-none absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
        <Input
          autoFocus
          className="pl-8"
          placeholder="Telefon, e-poçt və ya ad…"
          value={q}
          onChange={(e) => { setQ(e.target.value); setParams(e.target.value ? { q: e.target.value } : {}, { replace: true }) }}
        />
      </div>

      {term.length < 2 ? (
        <p className="text-sm text-muted-foreground">Axtarmaq üçün ən azı 2 simvol yazın.</p>
      ) : isLoading ? <LoadingState /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : rows.length === 0 ? (
        <EmptyState title="Bu məlumatla cihaz istifadəçisi tapılmadı" />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {rows.map((r) => <SupportCard key={r.device_user_id} row={r} />)}
        </div>
      )}
    </div>
  )
}

function Check({ ok, label, hint }: { ok: boolean; label: string; hint?: string }) {
  return (
    <div className="flex items-start gap-2 text-sm">
      {ok ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> : <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-destructive" />}
      <div>
        <span className={ok ? '' : 'font-medium text-destructive'}>{label}</span>
        {hint && !ok && <p className="text-xs text-muted-foreground">{hint}</p>}
      </div>
    </div>
  )
}

function SupportCard({ row }: { row: Resident }) {
  const { hasPermission } = useAuth()
  const canDiag = hasPermission(PERM.devicesDiagnostics)
  const canCommands = hasPermission(PERM.commandsView)
  const ping = usePingDevice(row.device.id)
  const [pingResult, setPingResult] = useState<PingResult | null>(null)
  const commands = useDeviceCommands(canCommands ? row.device.id : 0, null)
  const mine = (commands.data?.data ?? []).filter((c) => c.user_id === row.user_id).slice(0, 3)

  const sub = row.subscription
  const subActive = !!sub && sub.status === 'active' && (!sub.ends_at || new Date(sub.ends_at) > new Date())
  const deviceActive = row.device.status === 'active'
  const online = pingResult ? pingResult.online : row.device.online
  const firstProblem = !deviceActive ? 'Cihaz söndürülüb və ya istismardan çıxarılıb'
    : !subActive ? 'Abunəlik aktiv deyil — sakin tətbiqdən yeniləməlidir (və ya pulsuz abunəlik verin)'
    : !online ? 'Cihaz şəbəkədə deyil — elektrik / SIM / GPS siqnalını yoxlayın'
    : null

  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="flex flex-wrap items-center gap-2 text-base">
          {row.full_name ?? row.email ?? row.phone_masked}
          <span className="text-xs font-normal text-muted-foreground">{[row.email, row.phone_masked].filter(Boolean).join(' · ')}</span>
        </CardTitle>
        <p className="text-sm">
          Cihaz: <Link to={`/devices/${row.device.id}`} className="font-medium text-primary hover:underline">{row.device.serial}</Link>
          {row.complex && <span className="text-muted-foreground"> · {row.complex.name}</span>}
          <span className="text-muted-foreground"> · {row.role === 'owner' ? 'sahib' : 'istifadəçi'}</span>
        </p>
      </CardHeader>
      <CardContent className="space-y-3">
        <div className={`rounded-md p-2 text-sm ${firstProblem ? 'bg-destructive/10 text-destructive' : 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300'}`}>
          {firstProblem ?? 'Hər şey qaydasındadır — problem çox güman ki, telefondadır (internet, məkan icazəsi, tətbiqin versiyası).'}
        </div>
        <div className="grid gap-1.5">
          <Check ok={deviceActive} label="Cihaz aktivdir" hint="Cihaz səhifəsində «Aktivləşdir»" />
          <Check ok={online} label={`Cihaz onlayndır${pingResult ? ' (indi yoxlanıldı)' : ''}`} hint="Elektrik, SIM balansı və antenanı yoxlayın" />
          <Check ok={subActive} label={sub ? `Abunəlik: ${sub.status === 'active' ? 'aktiv' : sub.status}${sub.ends_at ? ` · ${formatDateTime(sub.ends_at)}-ə qədər` : ''}` : 'Abunəlik yoxdur'} hint="Sakin ödəniş etməlidir və ya cihaz səhifəsindən pulsuz abunəlik verilə bilər" />
        </div>
        <p className="text-xs text-muted-foreground">Son uğurlu açılış: {row.last_open_at ? relativeTime(row.last_open_at) : 'heç vaxt'}</p>

        {canCommands && (
          <div className="space-y-1">
            <p className="text-xs font-semibold uppercase text-muted-foreground">Bu sakinin son cəhdləri</p>
            {commands.isLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : mine.length === 0 ? (
              <p className="text-xs text-muted-foreground">Son əmrlər arasında bu sakinin cəhdi yoxdur (abunəlik/icazə xətası tətbiqdə göstərilir, əmr yaranmır).</p>
            ) : mine.map((c) => (
              <div key={c.id} className="flex flex-wrap items-center gap-2 text-xs">
                <StatusBadge kind="command" value={c.state} />
                <span className="text-muted-foreground">{formatDateTime(c.requested_at)}</span>
                {c.failure_reason && <span className="text-destructive">{c.failure_reason}</span>}
              </div>
            ))}
          </div>
        )}

        <div className="flex flex-wrap gap-2 pt-1">
          {canDiag && (
            <Button size="sm" variant="outline" disabled={ping.isPending}
              onClick={() => ping.mutate(undefined, { onSuccess: setPingResult })}>
              {ping.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Radio className="h-4 w-4" />}
              Cihazı indi yoxla
            </Button>
          )}
          <Button size="sm" variant="ghost" asChild><Link to={`/devices/${row.device.id}`}>Cihaz səhifəsi →</Link></Button>
        </div>
        {pingResult && (
          <p className="text-xs text-muted-foreground">
            {!pingResult.reachable ? 'Traccar serveri cavab vermədi — bir azdan yenidən yoxlayın.'
              : !pingResult.found ? 'Cihaz Traccar-da tapılmadı — IMEI səhv ola bilər.'
              : `Traccar: ${pingResult.online ? 'onlayn' : 'oflayn'}${pingResult.last_online_at ? ` · son siqnal ${relativeTime(pingResult.last_online_at)}` : ''}`}
          </p>
        )}
      </CardContent>
    </Card>
  )
}
