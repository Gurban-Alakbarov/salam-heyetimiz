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
 * Delete a resident from the SYSTEM (residents.delete): revokes every roster membership, cancels
 * entitlements, kills sessions and soft-deletes the account. 409 while they still own a device.
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
    },
  })
}
