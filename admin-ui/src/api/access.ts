import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'
import type { AdminRole, Permission } from '@/types/api'

export interface RoleInfo {
  role: AdminRole
  label: string
  description: string
  is_super: boolean
  is_complex_scoped: boolean
  admin_count: number
  default_permissions: Permission[]
}
export interface PermissionGroup {
  group: string
  permissions: { key: Permission; label: string }[]
}
export interface UserPermissions {
  admin: { id: number; email: string; name: string; role: AdminRole }
  inherited: Permission[]
  additional: Permission[]
  revoked: Permission[]
  effective: Permission[]
}

export function useRoles() {
  return useQuery({ queryKey: ['access', 'roles'], queryFn: async () => (await api.get<{ data: RoleInfo[] }>('/admin/v1/access/roles')).data.data })
}
export function usePermissionCatalog() {
  return useQuery({ queryKey: ['access', 'permissions'], queryFn: async () => (await api.get<{ data: PermissionGroup[] }>('/admin/v1/access/permissions')).data.data })
}
export function useUpdateRole() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ role, permissions }: { role: AdminRole; permissions: Permission[] }) =>
      (await api.patch(`/admin/v1/access/roles/${role}`, { permissions })).data,
    onSuccess: () => qc.invalidateQueries({ queryKey: ['access', 'roles'] }),
  })
}
export function useUserPermissions(adminId: number | null) {
  return useQuery({
    queryKey: ['access', 'user', adminId],
    queryFn: async () => (await api.get<UserPermissions>(`/admin/v1/admins/${adminId}/permissions`)).data,
    enabled: adminId != null,
  })
}
export function useGrantPermission(adminId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (permission: Permission) => (await api.post<UserPermissions>(`/admin/v1/admins/${adminId}/permissions/grant`, { permission })).data,
    onSuccess: (d) => qc.setQueryData(['access', 'user', adminId], d),
  })
}
export function useRevokePermission(adminId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (permission: Permission) => (await api.post<UserPermissions>(`/admin/v1/admins/${adminId}/permissions/revoke`, { permission })).data,
    onSuccess: (d) => qc.setQueryData(['access', 'user', adminId], d),
  })
}
export function useResetPermissions(adminId: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async () => (await api.post<UserPermissions>(`/admin/v1/admins/${adminId}/permissions/reset`)).data,
    onSuccess: (d) => qc.setQueryData(['access', 'user', adminId], d),
  })
}
