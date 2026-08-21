import { Lock, LockOpen, RefreshCw } from 'lucide-react'
import { useDeviceCommands, useRelayTest } from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { CursorPagination } from '@/components/CursorPagination'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import type { CommandDirection, OpenCommand } from '@/types/api'

const directionLabel: Record<CommandDirection, string> = {
  open: 'Açılış',
  close: 'Bağlanış',
}

/**
 * Manual "Relay Bağla" is hidden for now: an open auto-releases the latch server-side, so there is
 * nothing left to close by hand. Kept behind a flag (not deleted) — the close direction still exists
 * end-to-end (backend, API, history), so flipping this back is the only step needed to restore it.
 */
const SHOW_CLOSE_BUTTON = false

export function CommandsTab({ deviceId }: { deviceId: number }) {
  const cursor = useCursor()
  const query = useDeviceCommands(deviceId, cursor.cursor)
  const rows = query.data?.data ?? []
  // The newest command only makes sense as "current" on the first page.
  const latest = cursor.pageIndex === 0 ? rows[0] : undefined

  return (
    <div className="space-y-4">
      <TestRelayCard
        deviceId={deviceId}
        latest={latest}
        onRefresh={() => query.refetch()}
        refreshing={query.isFetching}
      />

      {query.isLoading ? (
        <TableSkeleton cols={7} />
      ) : query.isError ? (
        <ErrorState error={query.error} onRetry={() => query.refetch()} />
      ) : rows.length === 0 ? (
        <EmptyState title="Açılış əmri yoxdur" />
      ) : (
        <Card className="overflow-hidden">
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Əmr</TableHead>
                <TableHead>İstiqamət</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Başlanğıc</TableHead>
                <TableHead>Bitmə</TableHead>
                <TableHead>Gecikmə</TableHead>
                <TableHead>Səbəb</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {rows.map((c) => (
                <TableRow key={c.id}>
                  <TableCell className="font-mono text-xs">{c.command_text ?? c.driver}</TableCell>
                  <TableCell>{c.direction ? directionLabel[c.direction] : '—'}</TableCell>
                  <TableCell>
                    <StatusBadge kind="command" value={c.state} />
                  </TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(c.requested_at)}</TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(c.completed_at)}</TableCell>
                  <TableCell className="text-muted-foreground">
                    {c.latency_ms !== null ? `${c.latency_ms} ms` : '—'}
                  </TableCell>
                  <TableCell className="text-muted-foreground">{c.failure_reason ?? '—'}</TableCell>
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

function TestRelayCard({
  deviceId,
  latest,
  onRefresh,
  refreshing,
}: {
  deviceId: number
  latest?: OpenCommand
  onRefresh: () => void
  refreshing: boolean
}) {
  const relay = useRelayTest(deviceId)
  const { toast } = useToast()

  const fire = (action: CommandDirection) => {
    relay.mutate(action, {
      onSuccess: () =>
        toast({
          variant: 'success',
          title: action === 'open' ? 'Relay aç əmri göndərildi' : 'Relay bağla əmri göndərildi',
        }),
      onError: (err) =>
        toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Card className="space-y-3 p-4">
      <div className="flex items-start justify-between gap-2">
        <div>
          <h3 className="font-medium">Relay testi</h3>
          <p className="text-sm text-muted-foreground">
            Əmr DeviceComm pipeline-ından keçir (mənbə: admin) və cihazın cavabı ilə təsdiqlənir.
          </p>
        </div>
        <Button variant="ghost" size="sm" onClick={onRefresh} disabled={refreshing}>
          <RefreshCw className={`h-4 w-4 ${refreshing ? 'animate-spin' : ''}`} />
          Yenilə
        </Button>
      </div>

      {/*
        "Relay Bağla" is intentionally not rendered: an open now auto-releases the latch server-side
        (TraccarDriver sends close_command once the device confirms the open executed), so a manual
        close has nothing left to do. The backend direction + endpoint are untouched — flip
        SHOW_CLOSE_BUTTON back to true to bring it back.
      */}
      <PermissionGate anyOf={[PERM.commandsTest]}>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" onClick={() => fire('open')} disabled={relay.isPending}>
            <LockOpen className="h-4 w-4" />
            Relay Aç
          </Button>
          {SHOW_CLOSE_BUTTON && (
            <Button size="sm" variant="outline" onClick={() => fire('close')} disabled={relay.isPending}>
              <Lock className="h-4 w-4" />
              Relay Bağla
            </Button>
          )}
        </div>
      </PermissionGate>

      {latest && (
        <div className="space-y-1 rounded-md border p-3 text-sm">
          <div className="flex flex-wrap items-center gap-2">
            <span className="text-muted-foreground">Son əmr:</span>
            <span className="font-mono text-xs">{latest.command_text ?? '—'}</span>
            {latest.direction && <span className="text-muted-foreground">({directionLabel[latest.direction]})</span>}
            <StatusBadge kind="command" value={latest.state} />
          </div>
          {latest.terminal_response && (
            <div>
              <span className="text-muted-foreground">Cavab: </span>
              {latest.terminal_response}
            </div>
          )}
          {latest.failure_reason && <div className="text-destructive">Səbəb: {latest.failure_reason}</div>}
        </div>
      )}
    </Card>
  )
}
