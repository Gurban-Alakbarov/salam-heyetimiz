import { useEffect, useState } from 'react'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import { Link } from 'react-router-dom'
import { Search } from 'lucide-react'
import { type ResidentArchiveEntry, useResidentArchive } from '@/api/residents'
import { CursorPagination } from '@/components/CursorPagination'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDate, formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'

const reasonLabels: Record<string, string> = {
  admin_deleted: 'Admin sildi',
  legacy_deleted: 'Köhnə silinmə',
}

/**
 * O10 — residents an admin deleted from the system, as they were at deletion (residents.archive.view). Kept for
 * 5 years (purge_after), then removed automatically. Read-only.
 */
export function ResidentArchivePanel() {
  const [q, setQ] = useState('')
  const [open, setOpen] = useState<ResidentArchiveEntry | null>(null)
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()
  const dq = useDebouncedValue(q)
  const query = useResidentArchive({ q: dq || undefined, cursor })

  useEffect(() => {
    reset()
  }, [q, reset])

  const rows = query.data?.data ?? []

  return (
    <div className="space-y-4">
      <p className="text-sm text-muted-foreground">
        Admin tərəfindən sistemdən silinmiş sakinlər — silinmə anındakı məlumatları ilə. Hər qeyd 5 il saxlanılır,
        sonra avtomatik silinir. Sakinin özünün tətbiqdən sildiyi hesablar arxivə düşmür.
      </p>
      <div className="relative max-w-xs">
        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input className="pl-9" placeholder="Telefon, e-poçt, ad və ya ID" value={q} onChange={(e) => setQ(e.target.value)} />
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={7} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Arxivdə sakin yoxdur" />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Ad</TableHead>
                <TableHead>Telefon</TableHead>
                <TableHead>E-poçt</TableHead>
                <TableHead>Cihazlar</TableHead>
                <TableHead>Silən</TableHead>
                <TableHead>Silinib</TableHead>
                <TableHead>Saxlanılır</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((r) => (
                <TableRow key={r.id} className="cursor-pointer" onClick={() => setOpen(r)}>
                  <TableCell className="font-medium">{r.full_name ?? <span className="text-muted-foreground">—</span>}</TableCell>
                  <TableCell className="select-all">{r.phone ?? '—'}</TableCell>
                  <TableCell className="select-all break-all text-muted-foreground">{r.email ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {(r.snapshot?.devices ?? []).map((d) => d.serial ?? `#${d.device_id}`).join(', ') || '—'}
                  </TableCell>
                  <TableCell className="text-muted-foreground">
                    {r.deleted_by?.email ?? <Badge variant="muted">{reasonLabels[r.reason] ?? r.reason}</Badge>}
                  </TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(r.archived_at)}</TableCell>
                  <TableCell className="text-muted-foreground">{formatDate(r.purge_after)} qədər</TableCell>
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

      <ArchiveDetailDialog entry={open} onClose={() => setOpen(null)} />
    </div>
  )
}

function ArchiveDetailDialog({ entry, onClose }: { entry: ResidentArchiveEntry | null; onClose: () => void }) {
  const devices = entry?.snapshot?.devices ?? []
  const complexes = entry?.snapshot?.complexes ?? []
  const family = entry?.snapshot?.family

  return (
    <Dialog open={entry !== null} onOpenChange={(next) => !next && onClose()}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{entry?.full_name ?? entry?.phone ?? 'Arxiv qeydi'}</DialogTitle>
          <DialogDescription>
            İstifadəçi #{entry?.user_id} · silinib {formatDateTime(entry?.archived_at ?? null)}
            {entry?.deleted_by ? ` · ${entry.deleted_by.email}` : ''} · {formatDate(entry?.purge_after ?? null)} tarixində avtomatik silinəcək
          </DialogDescription>
        </DialogHeader>

        <div className="space-y-4 text-sm">
          <dl className="grid grid-cols-[max-content_1fr] gap-x-4 gap-y-1">
            <dt className="text-muted-foreground">Telefon</dt><dd className="select-all">{entry?.phone ?? '—'}</dd>
            <dt className="text-muted-foreground">E-poçt</dt><dd className="select-all break-all">{entry?.email ?? '—'}</dd>
            <dt className="text-muted-foreground">Hesab növü</dt><dd>{entry?.account_type ?? '—'}</dd>
            <dt className="text-muted-foreground">Qeydiyyat</dt><dd>{formatDateTime(entry?.registered_at ?? null)}</dd>
            <dt className="text-muted-foreground">Son giriş</dt><dd>{formatDateTime(entry?.last_login_at ?? null)}</dd>
          </dl>

          <div>
            <div className="mb-1 font-medium">Silinmə anında cihazları</div>
            {devices.length === 0 ? (
              <div className="text-muted-foreground">Yox idi</div>
            ) : (
              <ul className="space-y-1">
                {devices.map((d) => (
                  <li key={d.device_id} className="rounded-md border p-2">
                    <Link to={`/devices/${d.device_id}`} className="font-medium hover:underline">{d.serial ?? `#${d.device_id}`}</Link>
                    {d.label ? <span className="text-muted-foreground"> · {d.label}</span> : null}
                    <div className="text-xs text-muted-foreground">
                      {d.role === 'owner' ? 'Sahib' : 'İstifadəçi'}
                      {d.via_family ? ' (ailə vasitəsilə)' : ''}
                      {d.complex?.name ? ` · ${d.complex.name}` : ''}
                      {d.subscription
                        ? ` · abunə: ${d.subscription.status}${d.subscription.ends_at ? `, ${formatDate(d.subscription.ends_at)} qədər` : ''}`
                        : ' · abunə yox'}
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </div>

          {complexes.length > 0 && (
            <div>
              <div className="mb-1 font-medium">Kompleksləri</div>
              <div className="text-muted-foreground">{complexes.map((c) => c.name ?? `#${c.id}`).join(', ')}</div>
            </div>
          )}

          {family && (family.head_of_member_ids.length > 0 || family.member_of_head_ids.length > 0) && (
            <div>
              <div className="mb-1 font-medium">Ailə</div>
              <div className="text-muted-foreground">
                {family.head_of_member_ids.length > 0 && <>Başçı idi — üzvlər: #{family.head_of_member_ids.join(', #')}. </>}
                {family.member_of_head_ids.length > 0 && <>Üzv idi — başçı: #{family.member_of_head_ids.join(', #')}.</>}
              </div>
            </div>
          )}
        </div>

        <div className="flex justify-end">
          <Button variant="outline" onClick={onClose}>Bağla</Button>
        </div>
      </DialogContent>
    </Dialog>
  )
}
