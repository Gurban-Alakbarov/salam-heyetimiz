import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type {
  CreateDeviceInput,
  DeviceAdmin,
  DeviceAdminDetail,
  DeviceDiagnostic,
  DeviceUser,
  OpenCommand,
  Paginated,
  TransferInput,
  UpdateDeviceInput,
  WhitelistChange,
} from '@/types/api'

export interface DeviceListParams {
  status?: string
  q?: string
  owner_user_id?: number
  region_id?: number
  limit?: number
  cursor?: string | null
}

export function useDevices(params: DeviceListParams) {
  return useQuery({
    queryKey: ['devices', params],
    queryFn: async () =>
      (await api.get<Paginated<DeviceAdmin>>('/admin/v1/devices', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

export function useDevice(id: number) {
  return useQuery({
    queryKey: ['device', id],
    queryFn: async () => (await api.get<DeviceAdminDetail>(`/admin/v1/devices/${id}`)).data,
    enabled: Number.isFinite(id) && id > 0,
  })
}

export function useDeviceDiagnostics(id: number, cursor: string | null) {
  return useQuery({
    queryKey: ['device-diagnostics', id, cursor],
    queryFn: async () =>
      (
        await api.get<Paginated<DeviceDiagnostic>>(`/admin/v1/devices/${id}/diagnostics`, {
          params: cleanParams({ cursor, limit: 25 }),
        })
      ).data,
    placeholderData: keepPreviousData,
  })
}

export function useDeviceCommands(id: number, cursor: string | null) {
  return useQuery({
    queryKey: ['device-commands', id, cursor],
    queryFn: async () =>
      (
        await api.get<Paginated<OpenCommand>>(`/admin/v1/devices/${id}/commands`, {
          params: cleanParams({ cursor, limit: 25 }),
        })
      ).data,
    placeholderData: keepPreviousData,
    // While a command is still in flight (queued/dispatching), poll so the Test Relay card and
    // history update live as the pipeline reaches opened/failed with the device's response.
    refetchInterval: (query) => {
      const rows = query.state.data?.data ?? []
      const inFlight = rows.some((c) => c.state === 'queued' || c.state === 'dispatching')
      return inFlight ? 2000 : false
    },
  })
}

export function useWhitelistQueue(id: number, cursor: string | null) {
  return useQuery({
    queryKey: ['device-whitelist', id, cursor],
    queryFn: async () =>
      (
        await api.get<Paginated<WhitelistChange>>(`/admin/v1/devices/${id}/whitelist-queue`, {
          params: cleanParams({ cursor, limit: 25 }),
        })
      ).data,
    placeholderData: keepPreviousData,
  })
}

// ---- mutations ----
function useDeviceInvalidation() {
  const qc = useQueryClient()
  return (id?: number) => {
    qc.invalidateQueries({ queryKey: ['devices'] })
    if (id) qc.invalidateQueries({ queryKey: ['device', id] })
  }
}

export function useCreateDevice() {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: CreateDeviceInput) =>
      (await api.post<DeviceAdmin>('/admin/v1/devices', input)).data,
    onSuccess: () => invalidate(),
  })
}

export function useUpdateDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: UpdateDeviceInput) =>
      (await api.patch<DeviceAdmin>(`/admin/v1/devices/${id}`, input)).data,
    onSuccess: () => invalidate(id),
  })
}

// Upload a barrier photo (multipart). The server optimises + stores it and returns the device with
// the new `image_url`. axios sets the multipart boundary automatically for a FormData body.
export function useUploadDeviceImage(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (file: File) => {
      const fd = new FormData()
      fd.append('image', file)
      return (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/image`, fd)).data
    },
    onSuccess: () => invalidate(id),
  })
}

export function useDeleteDeviceImage(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async () => (await api.delete<DeviceAdmin>(`/admin/v1/devices/${id}/image`)).data,
    onSuccess: () => invalidate(id),
  })
}

export function useEnableDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async () => (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/enable`)).data,
    onSuccess: () => invalidate(id),
  })
}

export function useDisableDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (reason: string) =>
      (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/disable`, { reason })).data,
    onSuccess: () => invalidate(id),
  })
}

export function useTransferDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: TransferInput) =>
      (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/transfer`, input)).data,
    onSuccess: () => invalidate(id),
  })
}

export function useDecommissionDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (reason: string) => {
      await api.delete(`/admin/v1/devices/${id}`, { data: { reason } })
    },
    onSuccess: () => invalidate(id),
  })
}

// ---- barrier / resident assignment (Roster) ----
export interface AssignOwnerInput {
  owner_phone: string
  location_label?: string
}

export function useAssignDevice(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: AssignOwnerInput) =>
      (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/assign`, input)).data,
    onSuccess: () => invalidate(id),
  })
}

export function useAddDeviceUser(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (phone: string) =>
      (await api.post<DeviceUser>(`/admin/v1/devices/${id}/users`, { phone })).data,
    onSuccess: () => invalidate(id),
  })
}

export function useRemoveDeviceUser(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (userId: number) => {
      await api.delete(`/admin/v1/devices/${id}/users/${userId}`)
    },
    onSuccess: () => invalidate(id),
  })
}

/** Activate/extend a resident's subscription WITHOUT a payment (subscriptions.manage). */
export function useGrantSubscription(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: { userId: number; term_days?: number; tier?: 'main' | 'additional' }) => {
      const { userId, ...body } = input
      await api.post(`/admin/v1/devices/${id}/users/${userId}/subscription`, body)
    },
    onSuccess: () => invalidate(id),
  })
}

export function useResyncWhitelist(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => {
      await api.post(`/admin/v1/devices/${id}/whitelist/resync`)
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-whitelist', id] }),
  })
}

// Fire an open/close relay command through the DeviceComm pipeline (Test Relay).
export function useRelayTest(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (action: 'open' | 'close') =>
      (await api.post<OpenCommand>(`/admin/v1/devices/${id}/relay`, { action })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-commands', id] }),
  })
}
