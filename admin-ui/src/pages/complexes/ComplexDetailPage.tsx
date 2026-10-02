import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ChevronLeft, Link2, Unlink, UserPlus, X } from 'lucide-react'
import { useAdmins } from '@/api/admins'
import {
  useAssignManager, useComplex, useComplexDeviceBinding, useComplexInvitations, useComplexMembers, useUnassignManager,
} from '@/api/complexes'
import { useDevices } from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { formatDate, formatDateTime } from '@/lib/format'
import { OsmMap } from '@/components/OsmMap'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'

export function ComplexDetailPage() {
  const { id } = useParams()
  const complexId = Number(id)
  const { data: c, isLoading, isError, error, refetch } = useComplex(complexId)
  const { data: admins } = useAdmins()
  const assign = useAssignManager(complexId)
  const unassign = useUnassignManager(complexId)
  const binding = useComplexDeviceBinding()
  const { toast } = useToast()
  const [pick, setPick] = useState('')
  const err = (e: unknown) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined })

  return (
    <div className="space-y-6">
      <Link to="/complexes" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ChevronLeft className="h-4 w-4" /> Komplekslər</Link>

      {isLoading ? <LoadingState /> : isError || !c ? <ErrorState error={error} onRetry={() => refetch()} /> : (
        <>
          <div className="flex items-center gap-3">
            <h1 className="text-2xl font-semibold tracking-tight">{c.name}</h1>
            <code className="text-sm text-muted-foreground">{c.code}</code>
            {!c.is_active && <Badge variant="muted">Deaktiv</Badge>}
            {c.legal_entity_application_id && <Badge variant="outline">Hüquqi müraciət #{c.legal_entity_application_id}</Badge>}
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard label="Cihaz" value={c.stats.devices} />
            <StatCard label="Onlayn" value={c.stats.devices_online} />
            <StatCard label="Sakin" value={c.stats.residents} />
            <StatCard label="Menecer" value={c.stats.managers} />
          </div>

          <div className="grid gap-6 lg:grid-cols-2">
            <Card>
              <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-base">Kompleks menecerləri (Komendant)</CardTitle>
                <PermissionGate anyOf={[PERM.complexesManage]}>
                  <div className="flex items-center gap-2">
                    <select aria-label="Menecer seç" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={pick} onChange={(e) => setPick(e.target.value)}>
                      <option value="">Admin seç…</option>
                      {(admins ?? []).filter((a) => a.role !== 'super_admin').map((a) => <option key={a.id} value={a.id}>{a.name} ({a.email})</option>)}
                    </select>
                    <Button size="sm" disabled={!pick || assign.isPending} onClick={() => assign.mutate(Number(pick), { onSuccess: () => { toast({ variant: 'success', title: 'Menecer təyin edildi' }); setPick('') }, onError: err })}>
                      <UserPlus className="h-4 w-4" /> Təyin et
                    </Button>
                  </div>
                </PermissionGate>
              </CardHeader>
              <CardContent className="space-y-1">
                {c.managers.length === 0 ? <p className="text-sm text-muted-foreground">Menecer təyin edilməyib.</p> : c.managers.map((m) => {
                  const linked = admins?.find((a) => a.id === m.id)?.mobile_user
                  return (
                    <div key={m.id} className="flex items-center justify-between text-sm">
                      <span>
                        {m.name} <span className="text-muted-foreground">— {m.email}</span>
                        {/* link state needs admins.view (the admins list); without it nothing is claimed */}
                        {admins && (linked ? <Badge variant="success" className="ml-2">Mobil: {linked.email}</Badge> : <Badge variant="muted" className="ml-2">Mobil hesab bağlı deyil</Badge>)}
                      </span>
                      <PermissionGate anyOf={[PERM.complexesManage]}>
                        <Button size="icon" variant="ghost" aria-label="Çıxar" onClick={() => unassign.mutate(m.id, { onSuccess: () => toast({ variant: 'success', title: 'Menecer çıxarıldı' }), onError: err })}>
                          <X className="h-4 w-4 text-destructive" />
                        </Button>
                      </PermissionGate>
                    </div>
                  )
                })}
                <p className="pt-2 text-xs text-muted-foreground">Mobil hesab "Adminlər" səhifəsində bağlanır.</p>
              </CardContent>
            </Card>

            <Card>
              <CardHeader className="pb-2"><CardTitle className="text-base">Lokasiya</CardTitle></CardHeader>
              <CardContent className="space-y-2">
                {c.address && <p className="text-sm text-muted-foreground">{c.address}</p>}
                <OsmMap latitude={c.latitude} longitude={c.longitude} height={220} />
              </CardContent>
            </Card>
          </div>

          <Card className="overflow-hidden">
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-base">Cihazlar ({c.devices.length})</CardTitle>
              <PermissionGate anyOf={[PERM.complexesManage]}>
                <BindDevice complexId={complexId} />
              </PermissionGate>
            </CardHeader>
            <CardContent className="px-0 pb-0">
              {c.devices.length === 0 ? <p className="px-6 pb-6 text-sm text-muted-foreground">Cihaz yoxdur.</p> : (
                <Table>
                  <TableHeader><TableRow><TableHead>Serial</TableHead><TableHead>Status</TableHead><TableHead>Rejim</TableHead><TableHead>Bağlantı</TableHead><TableHead>Sahib</TableHead><TableHead>Yer</TableHead><TableHead className="w-0" /></TableRow></TableHeader>
                  <TableBody>
                    {c.devices.map((d) => (
                      <TableRow key={d.id}>
                        <TableCell className="font-medium"><Link to={`/devices/${d.id}`} className="hover:underline">{d.serial}</Link></TableCell>
                        <TableCell><StatusBadge kind="device" value={d.status} /></TableCell>
                        <TableCell>{d.ownership_mode === 'complex' ? <Badge variant="secondary">Kompleks</Badge> : <Badge variant="muted">Şəxsi</Badge>}</TableCell>
                        <TableCell>{d.online ? <Badge variant="success">Onlayn</Badge> : <Badge variant="muted">Oflayn</Badge>}</TableCell>
                        <TableCell className="text-muted-foreground">{d.owner ?? '—'}</TableCell>
                        <TableCell className="text-muted-foreground">{d.location_label ?? '—'}</TableCell>
                        <TableCell className="pr-4">
                          <PermissionGate anyOf={[PERM.complexesManage]}>
                            <Button size="icon" variant="ghost" aria-label="Kompleksdən ayır" title="Kompleksdən ayır"
                              onClick={() => binding.mutate({ complexId, deviceId: d.id, bind: false }, { onSuccess: () => toast({ variant: 'success', title: 'Cihaz ayrıldı' }), onError: err })}>
                              <Unlink className="h-4 w-4 text-destructive" />
                            </Button>
                          </PermissionGate>
                        </TableCell>
                      </TableRow>
                    ))}
                  </TableBody>
                </Table>
              )}
            </CardContent>
          </Card>

          <PermissionGate anyOf={[PERM.residentsView]}>
            <MembersCard complexId={complexId} />
            <InvitationsCard complexId={complexId} />
          </PermissionGate>
        </>
      )}
    </div>
  )
}

/** Find an unbound device by serial and bind it to this complex (complexes.manage). */
function BindDevice({ complexId }: { complexId: number }) {
  const [q, setQ] = useState('')
  const { data } = useDevices({ q: q.trim() || undefined, limit: 5 })
  const binding = useComplexDeviceBinding()
  const { toast } = useToast()
  const candidates = q.trim().length >= 2 ? (data?.data ?? []).filter((d) => (d.complex_id ?? null) === null) : []

  return (
    <div className="relative">
      <Input placeholder="Cihaz bağla: serial…" value={q} onChange={(e) => setQ(e.target.value)} className="h-9 w-56" />
      {candidates.length > 0 && (
        <div className="absolute right-0 z-10 mt-1 w-72 rounded-md border bg-card p-1 shadow-md">
          {candidates.map((d) => (
            <button key={d.id} type="button" className="flex w-full items-center justify-between rounded px-2 py-1.5 text-left text-sm hover:bg-muted"
              onClick={() => binding.mutate({ complexId, deviceId: d.id, bind: true }, {
                onSuccess: () => { toast({ variant: 'success', title: 'Cihaz kompleksə bağlandı' }); setQ('') },
                onError: (e) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined }),
              })}>
              <span>{d.serial} <span className="text-muted-foreground">{d.location_label ?? ''}</span></span>
              <Link2 className="h-4 w-4" />
            </button>
          ))}
        </div>
      )}
    </div>
  )
}

function MembersCard({ complexId }: { complexId: number }) {
  const { data, isLoading, isError, error, refetch } = useComplexMembers(complexId)
  return (
    <Card className="overflow-hidden">
      <CardHeader className="pb-2"><CardTitle className="text-base">Sakinlər ({data?.length ?? 0})</CardTitle></CardHeader>
      <CardContent className="px-0 pb-0">
        {isLoading ? <LoadingState /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : !data || data.length === 0 ? <EmptyState title="Sakin yoxdur" /> : (
          <Table>
            <TableHeader><TableRow><TableHead>Ad</TableHead><TableHead>E-poçt</TableHead><TableHead>Telefon</TableHead><TableHead>Aktiv abunəlik</TableHead><TableHead>Qoşulub</TableHead></TableRow></TableHeader>
            <TableBody>
              {data.map((m) => (
                <TableRow key={m.user_id}>
                  <TableCell className="font-medium">{m.full_name ?? '—'}</TableCell>
                  <TableCell>{m.email ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground">{m.phone ?? '—'}</TableCell>
                  <TableCell>{m.active_subscriptions > 0 ? <Badge variant="success">{m.active_subscriptions}</Badge> : <Badge variant="muted">0</Badge>}</TableCell>
                  <TableCell className="text-muted-foreground">{formatDate(m.joined_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}

const invitationLabels: Record<string, [string, 'success' | 'warning' | 'muted' | 'destructive' | 'secondary']> = {
  pending: ['Gözləyir', 'warning'], accepted: ['Qəbul edilib', 'success'], expired: ['Vaxtı bitib', 'muted'],
  cancelled: ['Ləğv edilib', 'destructive'], declined: ['Rədd edilib', 'secondary'],
}

function InvitationsCard({ complexId }: { complexId: number }) {
  const [status, setStatus] = useState('')
  const { data, isLoading, isError, error, refetch } = useComplexInvitations(complexId, status)
  return (
    <Card className="overflow-hidden">
      <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
        <CardTitle className="text-base">Dəvətlər</CardTitle>
        <select aria-label="Dəvət statusu" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Hamısı</option>
          {Object.entries(invitationLabels).map(([k, [l]]) => <option key={k} value={k}>{l}</option>)}
        </select>
      </CardHeader>
      <CardContent className="px-0 pb-0">
        {isLoading ? <LoadingState /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : !data || data.length === 0 ? <EmptyState title="Dəvət yoxdur" /> : (
          <Table>
            <TableHeader><TableRow><TableHead>Ad</TableHead><TableHead>E-poçt</TableHead><TableHead>Status</TableHead><TableHead>Göndərilib</TableHead><TableHead>Bitmə</TableHead></TableRow></TableHeader>
            <TableBody>
              {data.map((i) => (
                <TableRow key={i.id}>
                  <TableCell className="font-medium">{[i.first_name, i.last_name].filter(Boolean).join(' ') || '—'}</TableCell>
                  <TableCell>{i.email ?? '—'}</TableCell>
                  <TableCell><Badge variant={invitationLabels[i.status]?.[1] ?? 'muted'}>{invitationLabels[i.status]?.[0] ?? i.status}</Badge></TableCell>
                  <TableCell className="text-muted-foreground">{i.send_count}×</TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(i.expires_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>
    </Card>
  )
}

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <Card><CardContent className="pt-6"><p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p><p className="text-2xl font-semibold">{value}</p></CardContent></Card>
  )
}
