import { type FormEvent, useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2, Mail, Search, Trash2, Wifi, WifiOff } from 'lucide-react'
import { useComplexList } from '@/api/complexes'
import { type Resident, useDeleteResident, useResidents, useSetResidentEmail } from '@/api/residents'
import { ApiError } from '@/lib/api'
import { PERM } from '@/auth/permissions'
import { useAuth } from '@/auth/useAuth'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { DeviceUserRole } from '@/types/api'
import { ResidentArchivePanel } from './ResidentArchivePanel'

const roleLabels: Record<DeviceUserRole, string> = { owner: 'Sahib', user: 'İstifadəçi' }

export function ResidentsPage() {
  const { hasPermission } = useAuth()

  return (
    <div className="space-y-5">
      <PageHeader title="Sakinlər" description="Bütün sakinlər — kompleks, cihaz statusu və abunəlik" />
      {hasPermission(PERM.residentsArchiveView) ? (
        <Tabs defaultValue="residents">
          <TabsList>
            <TabsTrigger value="residents">Sakinlər</TabsTrigger>
            <TabsTrigger value="archive">Arxiv (silinənlər)</TabsTrigger>
          </TabsList>
          <TabsContent value="residents"><ResidentsDirectory /></TabsContent>
          <TabsContent value="archive"><ResidentArchivePanel /></TabsContent>
        </Tabs>
      ) : (
        <ResidentsDirectory />
      )}
    </div>
  )
}

function ResidentsDirectory() {
  const { hasRole } = useAuth()
  // O10 — a complex_manager only removes a resident from its own complex; the account stays in the system.
  const complexScoped = hasRole('complex_manager')
  const [q, setQ] = useState('')
  const [complexId, setComplexId] = useState('')
  const [deleteTarget, setDeleteTarget] = useState<Resident | null>(null)
  const [emailTarget, setEmailTarget] = useState<Resident | null>(null)
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()
  const { data: complexes } = useComplexList()
  const query = useResidents({ q: q || undefined, complex_id: complexId ? Number(complexId) : undefined, cursor })
  const deleteResident = useDeleteResident()
  const { toast } = useToast()

  useEffect(() => {
    reset()
  }, [q, complexId, reset])

  const rows = query.data?.data ?? []

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap gap-3">
        <div className="relative max-w-xs flex-1">
          <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input className="pl-9" placeholder="Telefon, e-poçt və ya ad axtar" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <select className="h-10 rounded-md border border-input bg-card px-3 text-sm" value={complexId} onChange={(e) => setComplexId(e.target.value)}>
          <option value="">Bütün komplekslər</option>
          {(complexes ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
        </select>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={9} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Sakin tapılmadı" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Telefon</TableHead>
                <TableHead>E-poçt</TableHead>
                <TableHead>Kompleks</TableHead>
                <TableHead>Cihaz</TableHead>
                <TableHead>Bağlantı</TableHead>
                <TableHead>Rol</TableHead>
                <TableHead>Abunəlik</TableHead>
                <TableHead>Son açılış</TableHead>
                <TableHead className="w-0" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.device_user_id}>
                  <TableCell className="font-medium">{r.phone_masked}</TableCell>
                  <TableCell className="text-muted-foreground">
                    <div className="flex flex-wrap items-center gap-1.5">
                      {r.email ? <span className="select-all break-all">{r.email}</span> : '—'}
                      {/* K1: an unproven email can still be recorded / corrected; a verified one is the login. */}
                      {r.email && !r.email_verified && (
                        <Badge variant="muted" title="Sakin bu e-poçtu tətbiqdə hələ təsdiqləməyib">təsdiqlənməyib</Badge>
                      )}
                      {!r.email_verified && (
                        <PermissionGate anyOf={[PERM.residentsUpdate]}>
                          <Button
                            size="icon"
                            variant="ghost"
                            className="h-7 w-7"
                            aria-label="E-poçt yaz"
                            title={r.email ? 'E-poçtu düzəlt' : 'E-poçt yaz'}
                            onClick={() => setEmailTarget(r)}
                          >
                            <Mail className="h-3.5 w-3.5" />
                          </Button>
                        </PermissionGate>
                      )}
                    </div>
                  </TableCell>
                  <TableCell>{r.complex?.name ?? <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell>
                    <Link to={`/devices/${r.device.id}`} className="hover:underline">{r.device.serial}</Link>
                  </TableCell>
                  <TableCell>
                    {r.device.online ? (
                      <Badge variant="success" className="gap-1"><Wifi className="h-3.5 w-3.5" /> Onlayn</Badge>
                    ) : (
                      <Badge variant="muted" className="gap-1"><WifiOff className="h-3.5 w-3.5" /> Oflayn</Badge>
                    )}
                  </TableCell>
                  <TableCell>{roleLabels[r.role] ?? r.role}</TableCell>
                  <TableCell>
                    {r.subscription ? <StatusBadge kind="subscription" value={r.subscription.status} /> : <span className="text-xs text-muted-foreground">Yoxdur</span>}
                  </TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(r.last_open_at)}</TableCell>
                  <TableCell className="pr-4 text-right">
                    {/* Owners cannot be deleted while they still hold a device — the API refuses with 409. */}
                    {r.role !== 'owner' && (
                      <PermissionGate anyOf={[PERM.residentsDelete]}>
                        <Button
                          size="icon"
                          variant="ghost"
                          aria-label={complexScoped ? 'Sakini kompleksdən çıxar' : 'Sakini sistemdən sil'}
                          title={complexScoped ? 'Kompleksdən çıxar' : 'Sistemdən sil'}
                          onClick={() => setDeleteTarget(r)}
                        >
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </PermissionGate>
                    )}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
        <CursorPagination
          pageIndex={pageIndex}
          canPrev={canPrev}
          hasMore={query.data?.page.has_more ?? false}
          onPrev={prev}
          onNext={() => next(query.data?.page.next_cursor ?? null)}
          disabled={query.isFetching}
        />
      </Card>

      <ConfirmDialog
        open={deleteTarget !== null}
        onOpenChange={(open) => {
          if (!open) setDeleteTarget(null)
        }}
        title={complexScoped ? 'Sakini kompleksdən çıxar' : 'Sakini sistemdən sil'}
        description={
          complexScoped
            ? `${deleteTarget?.email ?? deleteTarget?.phone_masked ?? ''} — sakin kompleksinizdən çıxarılacaq: ` +
              'kompleksin cihazlarından (whitelist-dən də) çıxarılacaq və bu cihazlardakı abunəlikləri ləğv ediləcək. ' +
              'Hesabı sistemdə qalır.'
            : `${deleteTarget?.email ?? deleteTarget?.phone_masked ?? ''} — hesab sistemdən silinəcək: bütün cihazlardan ` +
              'çıxarılacaq (whitelist-dən də), abunəlikləri ləğv ediləcək və sessiyaları bağlanacaq. ' +
              'Məlumatları 5 il arxivdə saxlanılacaq. Cihaz sahibi olan sakini silmək olmaz — əvvəlcə cihazı köçürün.'
        }
        confirmLabel={complexScoped ? 'Kompleksdən çıxar' : 'Sistemdən sil'}
        confirmVariant="destructive"
        loading={deleteResident.isPending}
        onConfirm={() => {
          if (!deleteTarget) return
          deleteResident.mutate(deleteTarget.user_id, {
            onSuccess: () => {
              toast({ variant: 'success', title: complexScoped ? 'Sakin kompleksdən çıxarıldı' : 'Sakin sistemdən silindi (arxivə köçürüldü)' })
              setDeleteTarget(null)
            },
            onError: (err) =>
              toast({
                variant: 'destructive',
                title: 'Xəta',
                description: err instanceof ApiError ? err.message : undefined,
              }),
          })
        }}
      />

      <SetEmailDialog resident={emailTarget} onClose={() => setEmailTarget(null)} />
    </div>
  )
}

/**
 * K1 — record the email a phone-created owner will register with. Only that email can activate the
 * account in the app (the email code proves it); a phone number alone never does.
 */
function SetEmailDialog({ resident, onClose }: { resident: Resident | null; onClose: () => void }) {
  const [email, setEmail] = useState('')
  const setResidentEmail = useSetResidentEmail()
  const { toast } = useToast()

  useEffect(() => {
    setEmail(resident?.email ?? '')
  }, [resident])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!resident) return
    setResidentEmail.mutate(
      { userId: resident.user_id, email: email.trim() },
      {
        onSuccess: () => {
          toast({ variant: 'success', title: 'E-poçt yazıldı', description: 'Sakin indi bu e-poçt ilə qeydiyyatdan keçə bilər.' })
          onClose()
        },
        onError: (err) =>
          toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
      },
    )
  }

  return (
    <Dialog
      open={resident !== null}
      onOpenChange={(next) => {
        if (!next && !setResidentEmail.isPending) onClose()
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{resident?.email ? 'E-poçtu düzəlt' : 'E-poçt yaz'}</DialogTitle>
            <DialogDescription>
              {resident?.phone_masked} — sakin tətbiqdə yalnız bu e-poçt ilə qeydiyyatdan keçib hesabını (cihazları,
              abunəliyi ilə birlikdə) aktivləşdirə bilər. E-poçtu sakinin özündən öyrənin.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2 py-4">
            <Label htmlFor="resident-email">E-poçt</Label>
            <Input
              id="resident-email"
              type="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              placeholder="ad@example.com"
              required
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={onClose} disabled={setResidentEmail.isPending}>
              İmtina
            </Button>
            <Button type="submit" disabled={setResidentEmail.isPending || !email.trim()}>
              {setResidentEmail.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Yaz
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
