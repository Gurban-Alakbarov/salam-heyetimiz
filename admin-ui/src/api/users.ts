import { keepPreviousData, useQuery } from '@tanstack/react-query'
import { api } from '@/lib/api'
import { cleanParams } from '@/lib/params'
import type { PageMeta } from './applications'

// Mobile users directory (B11 backend: GET /admin/v1/users). complex_manager is scoped server-side.
export type AccountType = 'physical' | 'legal'
export interface MobileUser {
  id: number
  full_name: string | null
  email: string | null
  phone: string | null
  account_type: AccountType | null
  status: string
  email_verified: boolean
  is_komendant_linked: boolean
  complex_ids: number[]
  created_at: string | null
}
export interface UserListParams {
  q?: string
  account_type?: AccountType | 'none' | ''
  page?: number
  per_page?: number
}

export function useMobileUsers(params: UserListParams) {
  return useQuery({
    queryKey: ['mobile-users', params],
    queryFn: async () => (await api.get<{ data: MobileUser[]; meta: PageMeta }>('/admin/v1/users', { params: cleanParams({ ...params }) })).data,
    placeholderData: keepPreviousData,
  })
}
