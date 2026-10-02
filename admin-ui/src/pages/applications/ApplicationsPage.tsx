import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2 } from 'lucide-react'
import {
  type IndividualApplication, type IndividualStatus, type LegalApplication,
  useApproveLegalApplication, useIndividualApplications, useLegalApplications, useRejectLegalApplication, useUpdateIndividualApplication,
} from '@/api/applications'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { OsmMap } from '@/components/OsmMap'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'

type Variant = 'success' | 'warning' | 'muted' | 'destructive' | 'secondary'
const individualLabels: Record<IndividualStatus, [string, Variant]> = {
  new: ['Yeni', 'warning'], contacted: ['Əlaqə saxlanılıb', 'secondary'], in_progress: ['İcradadır', 'secondary'],
  installed: ['Quraşdırılıb', 'success'], rejected: ['Rədd edilib', 'destructive'],
}
const legalLabels: Record<LegalApplication['status'], [string, Variant]> = {
  pending: ['Gözləyir', 'warning'], approved: ['Təsdiqlənib', 'success'], rejected: ['Rədd edilib', 'destructive'],
}

/** Registration applications (B11 UI over B9): Fiziki and Hüquqi kept in separate tabs / models. */
export function ApplicationsPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold tracking-tight">Müraciətlər</h1>
      <Tabs defaultValue="legal">
        <TabsList>
          <TabsTrigger value="legal">Hüquqi şəxs</TabsTrigger>
          <TabsTrigger value="individual">Fiziki şəxs</TabsTrigger>
        </TabsList>
        <TabsContent value="legal"><LegalTab /></TabsContent>
        <TabsContent value="individual"><IndividualTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function StatusFilter({ value, onChange, options }: { value: string; onChange: (v: string) => void; options: [string, string][] }) {
  return (
    <select aria-label="Status filtri" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={value} onChange={(e) => onChange(e.target.value)}>
      <option value="">Bütün statuslar</option>
      {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
    </select>
  )
}

function Pager({ page, total, perPage, onPage }: { page: number; total: number; perPage: number; onPage: (p: number) => void }) {
  const pages = Math.max(1, Math.ceil(total / perPage))
  return (
    <div className="flex items-center justify-end gap-2 p-3 text-sm text-muted-foreground">
      <span>{total} nəticə · səhifə {page}/{pages}</span>
      <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => onPage(page - 1)}>Əvvəlki</Button>
      <Button size="sm" variant="outline" disabled={page >= pages} onClick={() => onPage(page + 1)}>Növbəti</Button>
    </div>
  )
}

function LegalTab() {
  const [status, setStatus] = useState('pending')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState<LegalApplication | null>(null)
  const { data, isLoading, isError, error, refetch } = useLegalApplications({ status, q, page })

  return (
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center gap-2 p-3">
        <StatusFilter value={status} onChange={(v) => { setStatus(v); setPage(1) }} options={Object.entries(legalLabels).map(([k, [l]]) => [k, l])} />
        <Input placeholder="Kompleks, hüquqi ad, VÖEN…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} className="max-w-xs" />
      </div>
      <CardContent className="px-0 pb-0">
        {isLoading ? <TableSkeleton /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : !data || data.data.length === 0 ? <EmptyState title="Müraciət yoxdur" /> : (
          <>
            <Table>
              <TableHeader><TableRow><TableHead>Kompleks</TableHead><TableHead>Hüquqi ad</TableHead><TableHead>VÖEN</TableHead><TableHead>Əlaqə</TableHead><TableHead>Status</TableHead><TableHead>Tarix</TableHead></TableRow></TableHeader>
              <TableBody>
                {data.data.map((a) => (
                  <TableRow key={a.id} className="cursor-pointer" onClick={() => setOpen(a)}>
                    <TableCell className="font-medium">{a.complex_name}</TableCell>
                    <TableCell>{a.legal_name}</TableCell>
                    <TableCell><code>{a.voen}</code></TableCell>
                    <TableCell className="text-muted-foreground">{a.contact_person_name} · {a.contact_phone}</TableCell>
                    <TableCell><Badge variant={legalLabels[a.status][1]}>{legalLabels[a.status][0]}</Badge></TableCell>
                    <TableCell className="text-muted-foreground">{formatDateTime(a.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <Pager page={data.meta.page} total={data.meta.total} perPage={data.meta.per_page} onPage={setPage} />
          </>
        )}
      </CardContent>
      {open && <LegalDialog application={open} onClose={() => setOpen(null)} />}
    </Card>
  )
}

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="space-y-0.5">
      <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="text-sm break-words">{value ?? '—'}</p>
    </div>
  )
}

function LegalDialog({ application: a, onClose }: { application: LegalApplication; onClose: () => void }) {
  const { hasPermission } = useAuth()
  const { toast } = useToast()
  const approve = useApproveLegalApplication(a.id)
  const reject = useRejectLegalApplication(a.id)
  const [reason, setReason] = useState('')
  const [rejecting, setRejecting] = useState(false)
  const [approvedComplex, setApprovedComplex] = useState<LegalApplication['complex'] | null>(null)
  // Approval creates a complex → applications.manage AND complexes.manage (server-enforced too).
  const canApprove = hasPermission(PERM.applicationsManage) && hasPermission(PERM.complexesManage)
  const canReject = hasPermission(PERM.applicationsManage)
  const fail = (e: unknown) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined })
  const reasonError = reject.error instanceof ApiError ? reject.error.fieldError('reason') : undefined
  const complexId = approvedComplex?.id ?? a.complex_id
  const status: LegalApplication['status'] = approvedComplex ? 'approved' : a.status

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose() }}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{a.complex_name}</DialogTitle>
          <DialogDescription>Hüquqi şəxs müraciəti #{a.id} · <Badge variant={legalLabels[status][1]}>{legalLabels[status][0]}</Badge></DialogDescription>
        </DialogHeader>
        <div className="grid max-h-[60vh] gap-4 overflow-y-auto sm:grid-cols-2">
          <Field label="Hüquqi ad" value={a.legal_name} />
          <Field label="VÖEN" value={a.voen} />
          <Field label="Hüquqi ünvan" value={a.legal_address} />
          <Field label="Kompleks ünvanı" value={a.address} />
          <Field label="Əlaqə şəxsi" value={a.contact_person_name} />
          <Field label="Telefon / e-poçt" value={`${a.contact_phone} · ${a.contact_email}`} />
          <Field label="Mənzil sayı" value={a.apartments_count} />
          <Field label="Qeyd" value={a.note} />
          {a.rejection_reason && <Field label="Rədd səbəbi" value={a.rejection_reason} />}
          <div className="sm:col-span-2"><OsmMap latitude={a.location.latitude} longitude={a.location.longitude} height={220} /></div>
          {complexId && (
            <div className="sm:col-span-2 rounded-md bg-muted p-3 text-sm">
              Kompleks yaradılıb: <Link to={`/complexes/${complexId}`} className="font-medium text-primary hover:underline">kompleksə keç →</Link>
              <p className="mt-1 text-xs text-muted-foreground">Növbəti addımlar: Komendant (complex_manager) təyin edin və mobil hesabını "Adminlər"dən bağlayın, cihazları kompleksə bağlayın.</p>
            </div>
          )}
          {rejecting && (
            <div className="space-y-2 sm:col-span-2">
              <Label htmlFor="reject-reason">Rədd səbəbi (ərizəçiyə email ilə göndərilir)</Label>
              <Textarea id="reject-reason" value={reason} onChange={(e) => setReason(e.target.value)} rows={3} />
              {reasonError && <p className="text-xs text-destructive">{reasonError}</p>}
            </div>
          )}
        </div>
        <DialogFooter>
          {a.status === 'pending' && !approvedComplex && (
            <>
              {canReject && (rejecting ? (
                <Button variant="destructive" disabled={reject.isPending} onClick={() => reject.mutate(reason.trim(), { onSuccess: () => { toast({ variant: 'success', title: 'Müraciət rədd edildi' }); onClose() }, onError: fail })}>
                  {reject.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Rədd et
                </Button>
              ) : <Button variant="outline" onClick={() => setRejecting(true)}>Rədd et…</Button>)}
              {canApprove && !rejecting && (
                <Button disabled={approve.isPending} onClick={() => approve.mutate(undefined, { onSuccess: (res) => { setApprovedComplex(res.complex ?? null); toast({ variant: 'success', title: 'Təsdiqləndi — kompleks yaradıldı' }) }, onError: fail })}>
                  {approve.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Təsdiqlə və kompleks yarat
                </Button>
              )}
            </>
          )}
          <Button variant="ghost" onClick={onClose}>Bağla</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

function IndividualTab() {
  const [status, setStatus] = useState('')
  const [q, setQ] = useState('')
  const [page, setPage] = useState(1)
  const [open, setOpen] = useState<IndividualApplication | null>(null)
  const { data, isLoading, isError, error, refetch } = useIndividualApplications({ status, q, page })

  return (
    <Card className="overflow-hidden">
      <div className="flex flex-wrap items-center gap-2 p-3">
        <StatusFilter value={status} onChange={(v) => { setStatus(v); setPage(1) }} options={Object.entries(individualLabels).map(([k, [l]]) => [k, l])} />
        <Input placeholder="Ad, telefon, e-poçt…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} className="max-w-xs" />
      </div>
      <CardContent className="px-0 pb-0">
        {isLoading ? <TableSkeleton /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : !data || data.data.length === 0 ? <EmptyState title="Müraciət yoxdur" /> : (
          <>
            <Table>
              <TableHeader><TableRow><TableHead>Ad</TableHead><TableHead>Telefon</TableHead><TableHead>Ünvan</TableHead><TableHead>Status</TableHead><TableHead>Tarix</TableHead></TableRow></TableHeader>
              <TableBody>
                {data.data.map((a) => (
                  <TableRow key={a.id} className="cursor-pointer" onClick={() => setOpen(a)}>
                    <TableCell className="font-medium">{a.full_name}</TableCell>
                    <TableCell>{a.phone}</TableCell>
                    <TableCell className="text-muted-foreground">{a.address}</TableCell>
                    <TableCell><Badge variant={individualLabels[a.status][1]}>{individualLabels[a.status][0]}</Badge></TableCell>
                    <TableCell className="text-muted-foreground">{formatDateTime(a.created_at)}</TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <Pager page={data.meta.page} total={data.meta.total} perPage={data.meta.per_page} onPage={setPage} />
          </>
        )}
      </CardContent>
      {open && <IndividualDialog application={open} onClose={() => setOpen(null)} />}
    </Card>
  )
}

function IndividualDialog({ application: a, onClose }: { application: IndividualApplication; onClose: () => void }) {
  const { hasPermission } = useAuth()
  const { toast } = useToast()
  const update = useUpdateIndividualApplication(a.id)
  const [next, setNext] = useState<IndividualStatus | ''>('')
  const [note, setNote] = useState(a.admin_note ?? '')
  const [reason, setReason] = useState('')
  const [deviceId, setDeviceId] = useState('')
  const canManage = hasPermission(PERM.applicationsManage)
  const fieldErr = (f: string) => (update.error instanceof ApiError ? update.error.fieldError(f) : undefined)

  const submit = () => {
    if (!next) return
    update.mutate(
      { status: next, admin_note: note.trim() || null, rejection_reason: next === 'rejected' ? reason.trim() : null, device_id: next === 'installed' && deviceId ? Number(deviceId) : null },
      {
        onSuccess: () => { toast({ variant: 'success', title: 'Status yeniləndi' }); onClose() },
        onError: (e) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined }),
      },
    )
  }

  return (
    <Dialog open onOpenChange={(o) => { if (!o) onClose() }}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{a.full_name}</DialogTitle>
          <DialogDescription>Fiziki şəxs müraciəti #{a.id} · <Badge variant={individualLabels[a.status][1]}>{individualLabels[a.status][0]}</Badge></DialogDescription>
        </DialogHeader>
        <div className="grid max-h-[60vh] gap-4 overflow-y-auto sm:grid-cols-2">
          <Field label="Telefon" value={a.phone} />
          <Field label="E-poçt" value={a.email} />
          <Field label="Ünvan" value={a.address} />
          <Field label="Qeyd" value={a.note} />
          {a.device_id && <Field label="Quraşdırılmış cihaz" value={<Link to={`/devices/${a.device_id}`} className="text-primary hover:underline">#{a.device_id}</Link>} />}
          {a.rejection_reason && <Field label="Rədd səbəbi" value={a.rejection_reason} />}
          <div className="sm:col-span-2"><OsmMap latitude={a.location.latitude} longitude={a.location.longitude} height={220} /></div>
          {canManage && a.next_statuses.length > 0 && (
            <div className="grid gap-3 sm:col-span-2 sm:grid-cols-2">
              <div className="space-y-1">
                <Label htmlFor="next-status">Yeni status</Label>
                <select id="next-status" className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm" value={next} onChange={(e) => setNext(e.target.value as IndividualStatus)}>
                  <option value="">Seçin…</option>
                  {a.next_statuses.map((s) => <option key={s} value={s}>{individualLabels[s][0]}</option>)}
                </select>
              </div>
              {next === 'installed' && (
                <div className="space-y-1">
                  <Label htmlFor="inst-device">Quraşdırılan cihaz ID (şəxsi rejim, opsional)</Label>
                  <Input id="inst-device" inputMode="numeric" value={deviceId} onChange={(e) => setDeviceId(e.target.value.replace(/\D/g, ''))} />
                  {fieldErr('device_id') && <p className="text-xs text-destructive">{fieldErr('device_id')}</p>}
                </div>
              )}
              {next === 'rejected' && (
                <div className="space-y-1 sm:col-span-2">
                  <Label htmlFor="ind-reason">Rədd səbəbi</Label>
                  <Textarea id="ind-reason" rows={2} value={reason} onChange={(e) => setReason(e.target.value)} />
                  {fieldErr('rejection_reason') && <p className="text-xs text-destructive">{fieldErr('rejection_reason')}</p>}
                </div>
              )}
              <div className="space-y-1 sm:col-span-2">
                <Label htmlFor="ind-note">Admin qeydi</Label>
                <Textarea id="ind-note" rows={2} value={note} onChange={(e) => setNote(e.target.value)} />
              </div>
            </div>
          )}
        </div>
        <DialogFooter>
          {canManage && a.next_statuses.length > 0 && (
            <Button disabled={!next || update.isPending} onClick={submit}>{update.isPending && <Loader2 className="h-4 w-4 animate-spin" />} Yadda saxla</Button>
          )}
          <Button variant="ghost" onClick={onClose}>Bağla</Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
