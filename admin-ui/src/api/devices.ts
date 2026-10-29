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

export interface DeviceLookups {
  device_models: { id: number; vendor: string; model_code: string; default_driver_type: string | null }[]
  sim_operators: { id: number; name: string }[]
  regions: { id: number; name: string }[]
}

/** Choices for the device form (models, SIM operators, regions) — no numeric ids to type. */
export function useDeviceLookups() {
  return useQuery({
    queryKey: ['device-lookups'],
    queryFn: async () => (await api.get<DeviceLookups>('/admin/v1/devices/lookups')).data,
    staleTime: 5 * 60_000,
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
    enabled: id > 0,
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

/** B11 — switch the access model (private ↔ complex); the server enforces the B4 invariants (409). */
export function useChangeOwnershipMode(id: number) {
  const invalidate = useDeviceInvalidation()
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (mode: 'private' | 'complex') =>
      (await api.post<DeviceAdmin>(`/admin/v1/devices/${id}/ownership-mode`, { ownership_mode: mode })).data,
    onSuccess: () => {
      invalidate(id)
      qc.invalidateQueries({ queryKey: ['complex'] })
    },
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
  /** K1 — the owner registers in the app with this email (the email code proves the account is theirs). */
  owner_email?: string
  location_label?: string
}

export interface AddDeviceUserInput {
  phone: string
  /** K1 — same as AssignOwnerInput.owner_email, for a resident. */
  email?: string
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
    mutationFn: async (input: AddDeviceUserInput) =>
      (await api.post<DeviceUser>(`/admin/v1/devices/${id}/users`, input)).data,
    onSuccess: () => invalidate(id),
  })
}

/**
 * Y5 — move a resident to another gate (residents.update): this gate's access ends, the target's starts, and the
 * remaining paid time moves with them (no new payment). Returns the resident's row on the target gate.
 */
export function useMoveDeviceUser(id: number) {
  const invalidate = useDeviceInvalidation()
  return useMutation({
    mutationFn: async (input: { userId: number; targetDeviceId: number }) =>
      (
        await api.post<DeviceUser>(`/admin/v1/devices/${id}/users/${input.userId}/move`, {
          target_device_id: input.targetDeviceId,
        })
      ).data,
    onSuccess: (_row, input) => {
      invalidate(id)
      invalidate(input.targetDeviceId)
    },
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

export interface PingResult {
  /** false: Traccar itself did not answer. */
  reachable: boolean
  /** the device exists in Traccar (by IMEI) */
  found: boolean
  status: string | null
  online: boolean
  last_online_at: string | null
}

/** "Is the barrier reachable right now?" — asks Traccar live and records an admin_ping diagnostic. */
export function usePingDevice(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post<PingResult>(`/admin/v1/devices/${id}/ping`)).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['device-diagnostics', id] }),
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
