import { useEffect, useState } from 'react'
import { MoreVertical, Plus, Search } from 'lucide-react'
import { useRevokeVisitorLink, useVisitorLinks } from '@/api/visitorLinks'
import { PERM } from '@/auth/permissions'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { CursorPagination } from '@/components/CursorPagination'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import type { VisitorLinkAdmin } from '@/types/api'
import { CreateVisitorLinkDialog } from './CreateVisitorLinkDialog'
import { VisitorLinkDetailsSheet } from './VisitorLinkDetailsSheet'
import { ACCESS_TYPE_OPTIONS, PURPOSE_OPTIONS, accessTypeLabel, purposeLabel, usageLabel } from './labels'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'active', label: 'Aktiv' },
  { value: 'expired', label: 'Vaxtı bitib' },
  { value: 'revoked', label: 'Ləğv edilib' },
]

const CREATED_BY_OPTIONS = [
  { value: '', label: 'Hamısı (yaradan)' },
  { value: 'admin', label: 'Admin' },
  { value: 'resident', label: 'Sakin' },
]

export function VisitorLinksPanel({ deviceId, deviceLabel }: { deviceId: number; deviceLabel?: string }) {
  const { toast } = useToast()
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const [purpose, setPurpose] = useState('')
  const [accessType, setAccessType] = useState('')
  const [createdBy, setCreatedBy] = useState('')
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const debouncedQ = useDebouncedValue(q)
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  useEffect(() => {
    reset()
  }, [debouncedQ, status, purpose, accessType, createdBy, from, to, reset])

  const query = useVisitorLinks({
    device_id: deviceId,
    q: debouncedQ || undefined,
    status: status || undefined,
    purpose: purpose || undefined,
    access_type: accessType || undefined,
    created_by: createdBy || undefined,
    created_from: from || undefined,
    created_to: to || undefined,
    cursor,
    limit: 25,
  })
  const rows = query.data?.data ?? []

  const [createOpen, setCreateOpen] = useState(false)
  const [detailsLink, setDetailsLink] = useState<VisitorLinkAdmin | null>(null)
  const [revokeTarget, setRevokeTarget] = useState<VisitorLinkAdmin | null>(null)
  const revoke = useRevokeVisitorLink()

  return (
    <div className="space-y-4">
      <div className="flex items-center justify-between">
        <p className="text-sm text-muted-foreground">Bu barrier üçün paylaşıla bilən qonaq linkləri.</p>
        <PermissionGate anyOf={[PERM.visitorLinksManage]}>
          <Button size="sm" onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4" />
            Qonaq linki
          </Button>
        </PermissionGate>
      </div>

      {/* Filters */}
      <div className="flex flex-col gap-3">
        <div className="relative">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input className="pl-9" placeholder="Qonağın adı üzrə axtar…" value={q} onChange={(e) => setQ(e.target.value)} />
        </div>
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <Select value={status} onChange={(e) => setStatus(e.target.value)} aria-label="Status">
            {STATUS_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </Select>
          <Select value={purpose} onChange={(e) => setPurpose(e.target.value)} aria-label="Məqsəd">
            <option value="">Bütün məqsədlər</option>
            {PURPOSE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </Select>
          <Select value={accessType} onChange={(e) => setAccessType(e.target.value)} aria-label="Giriş növü">
            <option value="">Bütün növlər</option>
            {ACCESS_TYPE_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </Select>
          <Select value={createdBy} onChange={(e) => setCreatedBy(e.target.value)} aria-label="Yaradan">
            {CREATED_BY_OPTIONS.map((o) => (
              <option key={o.value} value={o.value}>
                {o.label}
              </option>
            ))}
          </Select>
        </div>
        <div className="grid grid-cols-2 gap-3 sm:max-w-md">
          <div className="space-y-1">
            <span className="text-xs text-muted-foreground">Tarixdən</span>
            <Input type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
          </div>
          <div className="space-y-1">
            <span className="text-xs text-muted-foreground">Tarixə</span>
            <Input type="date" value={to} onChange={(e) => setTo(e.target.value)} />
          </div>
        </div>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={9} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Qonaq linki yoxdur" description="Filtri dəyişin və ya yeni link yaradın." />
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Status</TableHead>
                  <TableHead>Qonaq</TableHead>
                  <TableHead>Məqsəd</TableHead>
                  <TableHead>Növ</TableHead>
                  <TableHead>İstifadə</TableHead>
                  <TableHead>Bitmə</TableHead>
                  <TableHead>Yaradan</TableHead>
                  <TableHead>Yaradılıb</TableHead>
                  <TableHead>Son istifadə</TableHead>
                  <TableHead className="w-10" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((link) => (
                  <TableRow key={link.id}>
                    <TableCell>
                      <StatusBadge kind="visitor" value={link.status} />
                    </TableCell>
                    <TableCell>{link.visitor_name ?? '—'}</TableCell>
                    <TableCell className="text-muted-foreground">{purposeLabel(link.purpose)}</TableCell>
                    <TableCell className="text-muted-foreground">{accessTypeLabel(link.access_type)}</TableCell>
                    <TableCell className="text-muted-foreground">{usageLabel(link.usage_count, link.max_usage)}</TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(link.expires_at)}</TableCell>
                    <TableCell className="text-muted-foreground">{link.created_by.label ?? (link.created_by.type === 'admin' ? 'admin' : 'sakin')}</TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(link.created_at)}</TableCell>
                    <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(link.last_used_at)}</TableCell>
                    <TableCell>
                      <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                          <Button variant="ghost" size="icon" aria-label="Əməliyyatlar">
                            <MoreVertical className="h-4 w-4" />
                          </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                          <DropdownMenuItem onSelect={() => setDetailsLink(link)}>
                            Detallar və jurnal
                          </DropdownMenuItem>
                          {link.status === 'active' && (
                            <PermissionGate anyOf={[PERM.visitorLinksManage]}>
                              <DropdownMenuSeparator />
                              <DropdownMenuItem className="text-destructive" onSelect={() => setRevokeTarget(link)}>
                                Ləğv et
                              </DropdownMenuItem>
                            </PermissionGate>
                          )}
                        </DropdownMenuContent>
                      </DropdownMenu>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
            <CursorPagination
              pageIndex={pageIndex}
              canPrev={canPrev}
              hasMore={query.data?.page.has_more ?? false}
              onPrev={prev}
              onNext={() => next(query.data?.page.next_cursor ?? null)}
              disabled={query.isFetching}
            />
          </>
        )}
      </Card>

      <CreateVisitorLinkDialog deviceId={deviceId} deviceLabel={deviceLabel} open={createOpen} onOpenChange={setCreateOpen} />

      <VisitorLinkDetailsSheet link={detailsLink} open={detailsLink !== null} onOpenChange={(o) => !o && setDetailsLink(null)} />

      <ConfirmDialog
        open={revokeTarget !== null}
        onOpenChange={(o) => !o && setRevokeTarget(null)}
        title="Qonaq linkini ləğv et"
        description="Ləğv edildikdən sonra bu link şlaqbaumu aça bilməyəcək. Bu əməliyyat geri qaytarıla bilməz."
        confirmLabel="Ləğv et"
        confirmVariant="destructive"
        loading={revoke.isPending}
        onConfirm={() => {
          if (!revokeTarget) return
          revoke.mutate(revokeTarget.id, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Link ləğv edildi' })
              setRevokeTarget(null)
            },
            onError: (err) =>
              toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
          })
        }}
      />
    </div>
  )
}
