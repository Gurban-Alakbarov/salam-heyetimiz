import { Link, useParams, useSearchParams } from 'react-router-dom'
import { ChevronLeft } from 'lucide-react'
import { useDevice } from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState, LoadingState } from '@/components/states'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { formatDateTime, signalLabel } from '@/lib/format'
import type { DeviceAdminDetail } from '@/types/api'
import { DeviceActions } from './DeviceActions'
import { RosterSection } from './RosterSection'
import { CommandsTab } from './tabs/CommandsTab'
import { DiagnosticsTab } from './tabs/DiagnosticsTab'
import { VisitorLinksTab } from './tabs/VisitorLinksTab'
import { WhitelistTab } from './tabs/WhitelistTab'

function DetailRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  )
}

function Overview({ device }: { device: DeviceAdminDetail }) {
  return (
    <Card>
      <CardHeader className="pb-2">
        <CardTitle className="text-base">Ümumi məlumat</CardTitle>
      </CardHeader>
      <CardContent className="grid grid-cols-2 gap-x-6 sm:grid-cols-3 lg:grid-cols-4">
        <DetailRow label="Model" value={device.device_model ? `${device.device_model.vendor} ${device.device_model.model_code}` : '—'} />
        <DetailRow label="Sürücü" value={<span className="uppercase">{device.driver_type}</span>} />
        <DetailRow label="SIM" value={device.sim_phone} />
        <DetailRow label="Operator" value={device.sim_operator?.name} />
        <DetailRow label="Sahib" value={device.owner?.phone_masked} />
        <DetailRow
          label="Sahib e-poçt"
          value={device.owner?.email ? <span className="select-all break-all">{device.owner.email}</span> : '—'}
        />
        <DetailRow label="Region" value={device.region?.name} />
        <DetailRow label="Ünvan" value={device.location_label} />
        <DetailRow label="Siqnal" value={signalLabel(device.last_signal_strength)} />
        <DetailRow
          label="Bağlantı"
          value={
            <span className={device.online ? 'font-medium text-emerald-600' : 'text-muted-foreground'}>
              {device.online ? 'Onlayn' : 'Oflayn'}
            </span>
          }
        />
        <DetailRow label="Son onlayn" value={formatDateTime(device.last_online_at)} />
        <DetailRow label="Whitelist (aktiv)" value={device.whitelist_capacity_used} />
        <DetailRow label="Yaradılıb" value={formatDateTime(device.created_at)} />
      </CardContent>
    </Card>
  )
}

const VALID_TABS = ['diagnostics', 'commands', 'whitelist', 'visitor-links']

export function DeviceDetailPage() {
  const { id } = useParams()
  const deviceId = Number(id)
  const [searchParams] = useSearchParams()
  // Deep-link support: the devices list "Visitor Links" quick action lands here with ?tab=visitor-links.
  const requestedTab = searchParams.get('tab')
  const defaultTab = requestedTab && VALID_TABS.includes(requestedTab) ? requestedTab : 'diagnostics'
  const { data: device, isLoading, isError, error, refetch } = useDevice(deviceId)

  return (
    <div className="space-y-6">
      <Link to="/devices" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ChevronLeft className="h-4 w-4" />
        Cihazlar
      </Link>

      {isLoading ? (
        <LoadingState />
      ) : isError || !device ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : (
        <>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div className="flex items-center gap-3">
              <h1 className="text-2xl font-semibold tracking-tight">{device.serial}</h1>
              <StatusBadge kind="device" value={device.status} />
            </div>
            <DeviceActions device={device} />
          </div>

          <Overview device={device} />
          <RosterSection device={device} />

          <Tabs defaultValue={defaultTab}>
            <TabsList>
              <TabsTrigger value="diagnostics">Diaqnostika</TabsTrigger>
              <TabsTrigger value="commands">Açılış əmrləri</TabsTrigger>
              <TabsTrigger value="whitelist">Whitelist növbəsi</TabsTrigger>
              <PermissionGate anyOf={[PERM.visitorLinksView]}>
                <TabsTrigger value="visitor-links">Qonaq linkləri</TabsTrigger>
              </PermissionGate>
            </TabsList>
            <TabsContent value="diagnostics">
              <DiagnosticsTab deviceId={device.id} />
            </TabsContent>
            <TabsContent value="commands">
              <CommandsTab deviceId={device.id} />
            </TabsContent>
            <TabsContent value="whitelist">
              <WhitelistTab deviceId={device.id} />
            </TabsContent>
            <PermissionGate anyOf={[PERM.visitorLinksView]}>
              <TabsContent value="visitor-links">
                <VisitorLinksTab device={device} />
              </TabsContent>
            </PermissionGate>
          </Tabs>
        </>
      )}
    </div>
  )
}
