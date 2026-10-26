import { keepPreviousData, useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { DeviceUserRole, Paginated } from '@/types/api'

export interface Resident {
  device_user_id: number
  user_id: number
  phone_masked: string
  /** Full email — admin surface only; null for phone-only accounts. */
  email: string | null
  /** K1 — false while the owner has not proven the email in the app (an admin may still record / correct it). */
  email_verified: boolean
  full_name: string | null
  role: DeviceUserRole
  last_open_at: string | null
  complex: { id: number; name: string } | null
  device: { id: number; serial: string; status: string; online: boolean }
  subscription: { status: string; ends_at: string | null } | null
}

export interface ResidentParams {
  q?: string
  complex_id?: number
  role?: string
  subscription_status?: string
  cursor?: string | null
  limit?: number
}

export function useResidents(params: ResidentParams) {
  return useQuery({
    queryKey: ['residents', params],
    queryFn: async () =>
      (await api.get<Paginated<Resident>>('/admin/v1/residents', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}

/**
 * Record the owner's email on a not-yet-verified account (residents.update, K1). An account created by phone
 * can only be activated in the app with THIS email — a phone number alone never hands it over. 409
 * email_taken / email_already_verified.
 */
export function useSetResidentEmail() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (input: { userId: number; email: string }) => {
      await api.put(`/admin/v1/residents/${input.userId}/email`, { email: input.email })
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['residents'] })
    },
  })
}

/**
 * residents.delete — a global admin deletes the account from the SYSTEM (roster revoked, entitlements cancelled,
 * sessions killed; archived for 5 years, then anonymised — 204; 409 while it still owns a device). A
 * complex_manager's call only removes the resident from ITS complex (O10) — 200 with what was revoked.
 */
export function useDeleteResident() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (userId: number) => {
      await api.delete(`/admin/v1/residents/${userId}`)
    },
    onSuccess: () => {
      void qc.invalidateQueries({ queryKey: ['residents'] })
      void qc.invalidateQueries({ queryKey: ['devices'] })
      void qc.invalidateQueries({ queryKey: ['resident-archive'] })
    },
  })
}

/** O10 — a resident an admin deleted from the system, as archived (residents.archive.view). */
export interface ResidentArchiveEntry {
  id: number
  user_id: number
  full_name: string | null
  phone: string | null
  email: string | null
  account_type: string | null
  registered_at: string | null
  last_login_at: string | null
  reason: 'admin_deleted' | 'legacy_deleted' | string
  deleted_by: { id: number; email: string; name: string } | null
  archived_at: string
  purge_after: string
  snapshot: {
    devices?: Array<{
      device_id: number
      serial: string | null
      label: string | null
      ownership_mode: string | null
      complex: { id: number; name: string | null } | null
      role: string
      via_family: boolean
      since: string | null
      subscription: { id: number; tier: string; status: string; starts_at: string | null; ends_at: string | null } | null
    }>
    complexes?: Array<{ id: number; name: string | null }>
    family?: { head_of_member_ids: number[]; member_of_head_ids: number[] }
  } | null
}

export function useResidentArchive(params: { q?: string; cursor?: string | null }, enabled = true) {
  return useQuery({
    queryKey: ['resident-archive', params],
    queryFn: async () =>
      (await api.get<Paginated<ResidentArchiveEntry>>('/admin/v1/resident-archive', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
    enabled,
  })
}
