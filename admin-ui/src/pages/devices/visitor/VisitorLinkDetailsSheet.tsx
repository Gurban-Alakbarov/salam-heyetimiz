import { useVisitorLinkUsages } from '@/api/visitorLinks'
import { CursorPagination } from '@/components/CursorPagination'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Sheet, SheetContent } from '@/components/ui/sheet'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { VisitorLinkAdmin } from '@/types/api'
import { accessTypeLabel, purposeLabel, usageLabel } from './labels'

function Field({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-1.5">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  )
}

export function VisitorLinkDetailsSheet({
  link,
  open,
  onOpenChange,
}: {
  link: VisitorLinkAdmin | null
  open: boolean
  onOpenChange: (open: boolean) => void
}) {
  return (
    <Sheet open={open} onOpenChange={onOpenChange}>
      <SheetContent side="right" className="w-full sm:max-w-xl">
        {link && <DetailsBody link={link} />}
      </SheetContent>
    </Sheet>
  )
}

function DetailsBody({ link }: { link: VisitorLinkAdmin }) {
  const cursor = useCursor()
  const usages = useVisitorLinkUsages(link.id, cursor.cursor)
  const rows = usages.data?.data ?? []

  const createdBy = link.created_by.label
    ? `${link.created_by.label} (${link.created_by.type === 'admin' ? 'admin' : 'sakin'})`
    : link.created_by.type === 'admin'
      ? 'admin'
      : 'sakin'

  return (
    <div className="flex h-full flex-col overflow-y-auto p-6">
      <div className="mb-4 flex items-center gap-2 pr-8">
        <h2 className="text-lg font-semibold">Qonaq linki</h2>
        <StatusBadge kind="visitor" value={link.status} />
      </div>

      <div className="grid grid-cols-2 gap-x-6">
        <Field label="Barrier" value={link.device?.label ?? link.device?.serial} />
        <Field label="Qonaq" value={link.visitor_name} />
        <Field label="Məqsəd" value={purposeLabel(link.purpose)} />
        <Field label="Giriş növü" value={accessTypeLabel(link.access_type)} />
        <Field label="İstifadə" value={usageLabel(link.usage_count, link.max_usage)} />
        <Field label="Bitmə" value={formatDateTime(link.expires_at)} />
        <Field label="Yaradan" value={createdBy} />
        <Field label="Yaradılıb" value={formatDateTime(link.created_at)} />
        <Field label="Son istifadə" value={formatDateTime(link.last_used_at)} />
        <Field label="Token prefiksi" value={link.token_prefix ? <span className="font-mono text-xs">{link.token_prefix}…</span> : '—'} />
      </div>

      <h3 className="mb-2 mt-6 text-sm font-semibold">İstifadə jurnalı</h3>
      {usages.isLoading ? (
        <TableSkeleton cols={4} />
      ) : usages.isError ? (
        <ErrorState error={usages.error} onRetry={() => usages.refetch()} />
      ) : rows.length === 0 ? (
        <EmptyState title="Cəhd yoxdur" description="Bu link hələ istifadə edilməyib." />
      ) : (
        <div className="overflow-hidden rounded-md border">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Vaxt</TableHead>
                <TableHead>Nəticə</TableHead>
                <TableHead>IP</TableHead>
                <TableHead>Brauzer</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((u) => (
                <TableRow key={u.id}>
                  <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(u.used_at)}</TableCell>
                  <TableCell>
                    <StatusBadge kind="visitorUsage" value={u.result} />
                  </TableCell>
                  <TableCell className="font-mono text-xs text-muted-foreground">{u.ip ?? '—'}</TableCell>
                  <TableCell className="max-w-[220px] truncate text-xs text-muted-foreground" title={u.user_agent ?? undefined}>
                    {u.user_agent ?? '—'}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <CursorPagination
            pageIndex={cursor.pageIndex}
            canPrev={cursor.canPrev}
            hasMore={usages.data?.page.has_more ?? false}
            onPrev={cursor.prev}
            onNext={() => cursor.next(usages.data?.page.next_cursor ?? null)}
            disabled={usages.isFetching}
          />
        </div>
      )}
    </div>
  )
}
