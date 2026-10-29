import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type DriverCode = 'traccar' | 'ble' | 'sms'

export interface CatalogDeviceModel {
  id: number
  vendor: string
  model_code: string
  default_driver_type: DriverCode | null
  fallback_open_driver: DriverCode | null
  /** max active users per device */
  whitelist_capacity: number
  sms_open_command: string | null
  open_command: string | null
  close_command: string | null
  command_result_confirm: boolean
  open_pulse_ms: number | null
  supports_clip: boolean
  supports_sms: boolean
  supports_mqtt: boolean
  notes: string | null
  is_active: boolean
  devices_count: number
}
export interface CatalogRegion { id: number; code: string; name: string; parent_id: number | null; is_active: boolean }
export interface CatalogOperator { id: number; code: string; name: string; country_iso: string; mcc_mnc: string | null; is_active: boolean }
export interface Catalog { device_models: CatalogDeviceModel[]; regions: CatalogRegion[]; sim_operators: CatalogOperator[] }

export type CatalogKind = 'device-models' | 'regions' | 'sim-operators'

/** Device catalog (catalog.manage): every row, inactive ones included. */
export function useCatalog() {
  return useQuery({ queryKey: ['catalog'], queryFn: async () => (await api.get<Catalog>('/admin/v1/catalog')).data })
}

/** Create (no id) or update a catalog row; refreshes the catalog and the device form's choices. */
export function useSaveCatalogRow(kind: CatalogKind) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ id, ...body }: { id?: number } & Record<string, unknown>) =>
      id ? (await api.patch(`/admin/v1/catalog/${kind}/${id}`, body)).data : (await api.post(`/admin/v1/catalog/${kind}`, body)).data,
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['catalog'] })
      void qc.invalidateQueries({ queryKey: ['device-lookups'] })
    },
  })
}
