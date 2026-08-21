import { useDeviceDiagnostics } from '@/api/devices'
import { CursorPagination } from '@/components/CursorPagination'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime, signalLabel } from '@/lib/format'
import { isLive } from '@/lib/online'
import { useCursor } from '@/lib/useCursor'
import type { DiagnosticSource } from '@/types/api'

const sourceLabels: Record<DiagnosticSource, string> = {
  scheduled_ping: 'Planlı yoxlama',
  open_dispatch: 'Açılış',
  admin_ping: 'Admin yoxlaması',
  device_initiated: 'Cihaz',
}

export function DiagnosticsTab({ deviceId }: { deviceId: number }) {
  const cursor = useCursor()
  const query = useDeviceDiagnostics(deviceId, cursor.cursor)
  const rows = query.data?.data ?? []

  if (query.isLoading) return <TableSkeleton cols={6} />
  if (query.isError) return <ErrorState error={query.error} onRetry={() => query.refetch()} />
  if (rows.length === 0) return <EmptyState title="Diaqnostika qeydi yoxdur" />

  return (
    <Card className="overflow-hidden">
      <Table>
        <TableHeader>
          <TableRow>
            <TableHead>Mənbə</TableHead>
            <TableHead>Onlayn</TableHead>
            <TableHead>Siqnal</TableHead>
            <TableHead>Batareya</TableHead>
            <TableHead>Firmware</TableHead>
            <TableHead>Vaxt</TableHead>
          </TableRow>
        </TableHeader>
        <TableBody>
          {rows.map((d) => (
            <TableRow key={d.id}>
              <TableCell>{sourceLabels[d.source] ?? d.source}</TableCell>
              <TableCell>
                {/* Same source of truth as the device summary: recency of the reading vs the offline window
                    (so the latest row always agrees with the "Bağlantı" status above — never contradicts). */}
                {isLive(d.reported_at) ? (
                  <Badge variant="success">Onlayn</Badge>
                ) : (
                  <Badge variant="muted">Oflayn</Badge>
                )}
              </TableCell>
              <TableCell className="text-muted-foreground">{signalLabel(d.signal_strength)}</TableCell>
              <TableCell className="text-muted-foreground">
                {d.battery_level !== null ? `${d.battery_level}%` : '—'}
              </TableCell>
              <TableCell className="text-muted-foreground">{d.firmware_version ?? '—'}</TableCell>
              <TableCell className="text-muted-foreground">{formatDateTime(d.reported_at)}</TableCell>
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
  )
}
