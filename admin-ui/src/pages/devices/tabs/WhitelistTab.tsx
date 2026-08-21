import { RefreshCw } from 'lucide-react'
import { useResyncWhitelist, useWhitelistQueue } from '@/api/devices'
import { ApiError } from '@/lib/api'
import { PERM } from '@/auth/permissions'
import { CursorPagination } from '@/components/CursorPagination'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { WhitelistAction } from '@/types/api'

const actionLabels: Record<WhitelistAction, string> = {
  add: 'Əlavə et',
  remove: 'Sil',
  clear: 'Təmizlə',
}

export function WhitelistTab({ deviceId }: { deviceId: number }) {
  const cursor = useCursor()
  const query = useWhitelistQueue(deviceId, cursor.cursor)
  const resync = useResyncWhitelist(deviceId)
  const { toast } = useToast()
  const rows = query.data?.data ?? []

  const onResync = () => {
    resync.mutate(undefined, {
      onSuccess: () => toast({ variant: 'success', title: 'Yenidən sinxronizasiya növbəyə alındı' }),
      onError: (err) =>
        toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <div className="space-y-3">
      <div className="flex justify-end">
        <PermissionGate anyOf={[PERM.whitelistManage]}>
          <Button variant="outline" size="sm" onClick={onResync} disabled={resync.isPending}>
            <RefreshCw className="h-4 w-4" />
            Yenidən sinxronla
          </Button>
        </PermissionGate>
      </div>

      {query.isLoading ? (
        <TableSkeleton cols={6} />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={() => query.refetch()} />
      ) : rows.length === 0 ? (
        <EmptyState title="Whitelist növbəsi boşdur" />
      ) : (
        <Card className="overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Əməliyyat</TableHead>
                <TableHead>Telefon</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Cəhd</TableHead>
                <TableHead>Son xəta</TableHead>
                <TableHead>Yaradılıb</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((w) => (
                <TableRow key={w.id}>
                  <TableCell>{actionLabels[w.action] ?? w.action}</TableCell>
                  <TableCell className="text-muted-foreground">{w.phone}</TableCell>
                  <TableCell>
                    <StatusBadge kind="whitelist" value={w.status} />
                  </TableCell>
                  <TableCell className="text-muted-foreground">{w.attempt_count}</TableCell>
                  <TableCell className="text-muted-foreground">{w.last_error ?? '—'}</TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(w.created_at)}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
          <CursorPagination
            pageIndex={cursor.pageIndex}
            canPrev={cursor.canPrev}
            hasMore={query.data?.page.has_more ?? false}
            onPrev={cursor.prev}
            onNext={() => cursor.next(query.data?.page.next_cursor ?? null)}
            disabled={query.isFetching}
          />
        </Card>
      )}
    </div>
  )
}
