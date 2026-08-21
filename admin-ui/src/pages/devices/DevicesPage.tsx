import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Link2, MoreVertical, Pencil, Plus, Search, Ticket, Wifi, WifiOff } from 'lucide-react'
import { useDevices } from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
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
import { formatDate } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import { useDebouncedValue } from '@/lib/useDebouncedValue'
import type { DeviceAdmin } from '@/types/api'
import { CreateVisitorLinkDialog } from './visitor/CreateVisitorLinkDialog'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'unassigned', label: 'Təyin olunmayıb' },
  { value: 'active', label: 'Aktiv' },
  { value: 'suspended', label: 'Dayandırılıb' },
  { value: 'disabled', label: 'Bağlı' },
  { value: 'decommissioned', label: 'Çıxarılıb' },
]

export function DevicesPage() {
  const [q, setQ] = useState('')
  const [status, setStatus] = useState('')
  const debouncedQ = useDebouncedValue(q)
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  useEffect(() => {
    reset()
  }, [debouncedQ, status, reset])

  const query = useDevices({
    q: debouncedQ || undefined,
    status: status || undefined,
    cursor,
    limit: 25,
  })

  const rows = query.data?.data ?? []
  const [createForDevice, setCreateForDevice] = useState<DeviceAdmin | null>(null)

  return (
    <div className="space-y-5">
      <PageHeader
        title="Cihazlar"
        description="Cihazların idarə edilməsi və axtarışı"
        actions={
          <PermissionGate anyOf={[PERM.devicesCreate]}>
            <Button asChild>
              <Link to="/devices/new">
                <Plus className="h-4 w-4" />
                Yeni cihaz
              </Link>
            </Button>
          </PermissionGate>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <div className="relative flex-1">
          <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
          <Input
            className="pl-9"
            placeholder="Serial / SIM / sahib telefonu üzrə axtar…"
            value={q}
            onChange={(e) => setQ(e.target.value)}
          />
        </div>
        <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </Select>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={6} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Cihaz tapılmadı" description="Filtri dəyişib yenidən cəhd edin." />
        ) : (
          <>
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Serial</TableHead>
                  <TableHead>Sahib</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Model</TableHead>
                  <TableHead>SIM</TableHead>
                  <TableHead>Onlayn</TableHead>
                  <TableHead>Yaradılıb</TableHead>
                  <TableHead className="w-10" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {rows.map((d) => (
                  <TableRow key={d.id}>
                    <TableCell>
                      <Link to={`/devices/${d.id}`} className="font-medium text-primary hover:underline">
                        {d.serial}
                      </Link>
                    </TableCell>
                    <TableCell className="text-muted-foreground">{d.owner?.phone_masked ?? '—'}</TableCell>
                    <TableCell>
                      <StatusBadge kind="device" value={d.status} />
                    </TableCell>
                    <TableCell className="text-muted-foreground">
                      {d.device_model ? `${d.device_model.vendor} ${d.device_model.model_code}` : '—'}
                    </TableCell>
                    <TableCell className="text-muted-foreground">{d.sim_phone ?? '—'}</TableCell>
                    <TableCell>
                      {d.online ? (
                        <span className="inline-flex items-center gap-1 text-xs text-success">
                          <Wifi className="h-3.5 w-3.5" /> Onlayn
                        </span>
                      ) : (
                        <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
                          <WifiOff className="h-3.5 w-3.5" /> Oflayn
                        </span>
                      )}
                    </TableCell>
                    <TableCell className="text-muted-foreground">{formatDate(d.created_at)}</TableCell>
                    <TableCell>
                      <DeviceRowActions device={d} onCreateVisitorLink={() => setCreateForDevice(d)} />
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

      {createForDevice && (
        <CreateVisitorLinkDialog
          key={createForDevice.id}
          deviceId={createForDevice.id}
          deviceLabel={createForDevice.location_label ?? createForDevice.serial}
          open={createForDevice !== null}
          onOpenChange={(o) => !o && setCreateForDevice(null)}
        />
      )}
    </div>
  )
}

/** Row quick actions (⋮): edit, jump to the Visitor Links tab, or create a link without leaving the list. */
function DeviceRowActions({ device, onCreateVisitorLink }: { device: DeviceAdmin; onCreateVisitorLink: () => void }) {
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label="Əməliyyatlar">
          <MoreVertical className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        <PermissionGate anyOf={[PERM.devicesUpdate]}>
          <DropdownMenuItem asChild>
            <Link to={`/devices/${device.id}/edit`}>
              <Pencil className="h-4 w-4" />
              Redaktə
            </Link>
          </DropdownMenuItem>
        </PermissionGate>
        <PermissionGate anyOf={[PERM.visitorLinksView]}>
          <DropdownMenuItem asChild>
            <Link to={`/devices/${device.id}?tab=visitor-links`}>
              <Ticket className="h-4 w-4" />
              Qonaq linkləri
            </Link>
          </DropdownMenuItem>
        </PermissionGate>
        <PermissionGate anyOf={[PERM.visitorLinksManage]}>
          <DropdownMenuSeparator />
          <DropdownMenuItem onSelect={onCreateVisitorLink}>
            <Link2 className="h-4 w-4" />
            Qonaq linki yarat
          </DropdownMenuItem>
        </PermissionGate>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
