import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ChevronLeft, UserPlus, X } from 'lucide-react'
import { useAdmins } from '@/api/admins'
import { useAssignManager, useComplex, useUnassignManager } from '@/api/complexes'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'

export function ComplexDetailPage() {
  const { id } = useParams()
  const complexId = Number(id)
  const { data: c, isLoading, isError, error, refetch } = useComplex(complexId)
  const { data: admins } = useAdmins()
  const assign = useAssignManager(complexId)
  const unassign = useUnassignManager(complexId)
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
          </div>

          <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <StatCard label="Cihaz" value={c.stats.devices} />
            <StatCard label="Onlayn" value={c.stats.devices_online} />
            <StatCard label="Sakin" value={c.stats.residents} />
            <StatCard label="Menecer" value={c.stats.managers} />
          </div>

          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <CardTitle className="text-base">Kompleks menecerləri</CardTitle>
              <PermissionGate anyOf={[PERM.complexesManage]}>
                <div className="flex items-center gap-2">
                  <select className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={pick} onChange={(e) => setPick(e.target.value)}>
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
              {c.managers.length === 0 ? <p className="text-sm text-muted-foreground">Menecer təyin edilməyib.</p> : c.managers.map((m) => (
                <div key={m.id} className="flex items-center justify-between text-sm">
                  <span>{m.name} <span className="text-muted-foreground">— {m.email}</span></span>
                  <PermissionGate anyOf={[PERM.complexesManage]}>
                    <Button size="icon" variant="ghost" aria-label="Çıxar" onClick={() => unassign.mutate(m.id, { onSuccess: () => toast({ variant: 'success', title: 'Menecer çıxarıldı' }), onError: err })}>
                      <X className="h-4 w-4 text-destructive" />
                    </Button>
                  </PermissionGate>
                </div>
              ))}
            </CardContent>
          </Card>

          <Card className="overflow-hidden">
            <CardHeader className="pb-2"><CardTitle className="text-base">Cihazlar ({c.devices.length})</CardTitle></CardHeader>
            <CardContent className="px-0 pb-0">
              {c.devices.length === 0 ? <p className="px-6 pb-6 text-sm text-muted-foreground">Cihaz yoxdur.</p> : (
                <Table>
                  <TableHeader><TableRow><TableHead>Serial</TableHead><TableHead>Status</TableHead><TableHead>Bağlantı</TableHead><TableHead>Sahib</TableHead><TableHead>Yer</TableHead></TableRow></TableHeader>
                  <TableBody>
                    {c.devices.map((d) => (
                      <TableRow key={d.id}>
                        <TableCell className="font-medium"><Link to={`/devices/${d.id}`} className="hover:underline">{d.serial}</Link></TableCell>
                        <TableCell><StatusBadge kind="device" value={d.status} /></TableCell>
                        <TableCell>{d.online ? <Badge variant="success">Onlayn</Badge> : <Badge variant="muted">Oflayn</Badge>}</TableCell>
                        <TableCell className="text-muted-foreground">{d.owner ?? '—'}</TableCell>
                        <TableCell className="text-muted-foreground">{d.location_label ?? '—'}</TableCell>
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

function StatCard({ label, value }: { label: string; value: number }) {
  return (
    <Card><CardContent className="pt-6"><p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p><p className="text-2xl font-semibold">{value}</p></CardContent></Card>
  )
}
