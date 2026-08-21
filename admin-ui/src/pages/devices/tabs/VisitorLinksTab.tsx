import type { DeviceAdminDetail } from '@/types/api'
import { VisitorLinksPanel } from '../visitor/VisitorLinksPanel'

export function VisitorLinksTab({ device }: { device: DeviceAdminDetail }) {
  return <VisitorLinksPanel deviceId={device.id} deviceLabel={device.location_label ?? device.serial} />
}
