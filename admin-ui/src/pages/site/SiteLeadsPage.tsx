import { type FormEvent, useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { type SiteLead, type SiteLeadStatus, useSiteLeads, useUpdateSiteLead } from '@/api/siteLeads'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { PageHeader } from '@/components/PageHeader'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

const STATUS: Record<SiteLeadStatus, [string, 'warning' | 'secondary' | 'muted']> = {
  new: ['Yeni', 'warning'],
  contacted: ['Əlaqə saxlanılıb', 'secondary'],
  closed: ['Bağlanıb', 'muted'],
}
const PER_PAGE = 25

function LeadDialog({ lead, onClose, canManage }: { lead: SiteLead | null; onClose: () => void; canManage: boolean }) {
  const { toast } = useToast()
  const update = useUpdateSiteLead()
  const [status, setStatus] = useState<SiteLeadStatus>('new')
  const [note, setNote] = useState('')

  useEffect(() => {
    setStatus(lead?.status ?? 'new')
    setNote(lead?.admin_note ?? '')
  }, [lead])

  const submit = (e: FormEvent) => {
    e.preventDefault()
    if (!lead) return
    update.mutate({ id: lead.id, status, admin_note: note.trim() || null }, {
      onSuccess: () => {
        toast({ variant: 'success', title: 'Müraciət yeniləndi' })
        onClose()
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Dialog open={lead !== null} onOpenChange={(open) => { if (!open) onClose() }}>
      <DialogContent>
        {lead && (
          <form onSubmit={submit} className="space-y-4">
            <DialogHeader>
              <DialogTitle>{lead.name}</DialogTitle>
              <DialogDescription>{formatDateTime(lead.created_at)} · {lead.locale.toUpperCase()}</DialogDescription>
            </DialogHeader>
            <dl className="grid gap-2 text-sm">
              <div><dt className="text-muted-foreground">E-poçt</dt><dd><a className="text-primary hover:underline" href={`mailto:${lead.email}`}>{lead.email}</a></dd></div>
              <div><dt className="text-muted-foreground">Telefon</dt><dd><a className="text-primary hover:underline" href={`tel:${lead.phone}`}>{lead.phone}</a></dd></div>
              <div><dt className="text-muted-foreground">Məkan</dt><dd>{lead.place}</dd></div>
              {lead.topic && <div><dt className="text-muted-foreground">Mövzu</dt><dd>{lead.topic}</dd></div>}
              {lead.message && <div><dt className="text-muted-foreground">Mesaj</dt><dd className="whitespace-pre-wrap">{lead.message}</dd></div>}
            </dl>
            {canManage && (
              <>
                <div className="space-y-1.5">
                  <Label htmlFor="lead-status">Status</Label>
                  <Select id="lead-status" value={status} onChange={(e) => setStatus(e.target.value as SiteLeadStatus)}>
                    {Object.entries(STATUS).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
                  </Select>
                </div>
                <div className="space-y-1.5">
                  <Label htmlFor="lead-note">Qeyd</Label>
                  <Textarea id="lead-note" rows={3} maxLength={1000} value={note} onChange={(e) => setNote(e.target.value)} />
                </div>
              </>
            )}
            <DialogFooter>
              <Button type="button" variant="outline" onClick={onClose}>Bağla</Button>
              {canManage && (
                <Button type="submit" disabled={update.isPending}>
                  {update.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}Yadda saxla
                </Button>
              )}
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}

export function SiteLeadsPage() {
  const { hasPermission } = useAuth()
  const [status, setStatus] = useState('')
  const [search, setSearch] = useState('')
  const [page, setPage] = useState(1)
  const [selected, setSelected] = useState<SiteLead | null>(null)
  const q = useDebouncedValue(search, 300)

  useEffect(() => {
    setPage(1)
  }, [status, q])

  const query = useSiteLeads({ status: status || undefined, q: q || undefined, page, per_page: PER_PAGE })
  const rows = query.data?.data ?? []
  const total = query.data?.meta.total ?? 0

  return (
    <div className="space-y-5">
      <PageHeader title="Sayt müraciətləri" description="Saytın əlaqə formasından gələn müraciətlər" />

      <div className="flex flex-col gap-3 sm:flex-row">
        <Input className="sm:w-72" placeholder="Ad, e-poçt və ya telefon" value={search} onChange={(e) => setSearch(e.target.value)} />
        <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
          <option value="">Bütün statuslar</option>
          {Object.entries(STATUS).map(([value, [label]]) => <option key={value} value={value}>{label}</option>)}
        </Select>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={6} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Müraciət yoxdur" />
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Tarix</TableHead>
                  <TableHead>Ad</TableHead>
                  <TableHead>Telefon</TableHead>
                  <TableHead>Məkan</TableHead>
                  <TableHead>Mövzu</TableHead>
                  <TableHead>Status</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((lead) => (
                  <TableRow key={lead.id} className="cursor-pointer" onClick={() => setSelected(lead)}>
                    <TableCell className="text-muted-foreground">{formatDateTime(lead.created_at)}</TableCell>
                    <TableCell className="font-medium">{lead.name}</TableCell>
                    <TableCell>{lead.phone}</TableCell>
                    <TableCell>{lead.place}</TableCell>
                    <TableCell className="text-muted-foreground">{lead.topic ?? '—'}</TableCell>
                    <TableCell><Badge variant={STATUS[lead.status][1]}>{STATUS[lead.status][0]}</Badge></TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            {total > PER_PAGE && (
              <div className="flex items-center justify-between border-t p-3 text-sm text-muted-foreground">
                <span>{total} müraciət</span>
                <div className="flex gap-2">
                  <Button variant="outline" size="sm" disabled={page <= 1} onClick={() => setPage(page - 1)}>Əvvəlki</Button>
                  <Button variant="outline" size="sm" disabled={page * PER_PAGE >= total} onClick={() => setPage(page + 1)}>Növbəti</Button>
                </div>
              </div>
            )}
          </>
        )}
      </Card>

      <LeadDialog lead={selected} onClose={() => setSelected(null)} canManage={hasPermission(PERM.applicationsManage)} />
    </div>
  )
}
