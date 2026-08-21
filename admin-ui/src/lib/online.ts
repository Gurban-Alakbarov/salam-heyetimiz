// Single source of truth for device connectivity on the client. A device (or a telemetry sample) is
// "live" if its last/own timestamp is within the offline window. Mirrors the backend
// `offline_threshold_minutes` (15) used by DeviceAdminResource.online, so the device summary and the
// diagnostics rows can never disagree.
export const OFFLINE_WINDOW_MS = 15 * 60 * 1000

export function isLive(iso: string | null): boolean {
  if (!iso) return false
  return Date.now() - new Date(iso).getTime() < OFFLINE_WINDOW_MS
}
