import { useState } from 'react'
import { Loader2, RotateCcw } from 'lucide-react'
import { useAdmins } from '@/api/admins'
import {
  useGrantPermission, usePermissionCatalog, useResetPermissions, useRevokePermission, useRoles, useUpdateRole,
  useUserPermissions, type RoleInfo,
} from '@/api/access'
import { ApiError } from '@/lib/api'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { useToast } from '@/components/ui/toast'
import type { AdminRole, Permission } from '@/types/api'

const roleLabels: Record<AdminRole, string> = {
  super_admin: 'Super Admin', technical: 'Texniki', operator: 'Operator',
  finance: 'Maliyyə', support: 'Dəstək', complex_manager: 'Kompleks Meneceri',
}

export function AccessControlPage() {
  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold tracking-tight">Giriş nəzarəti</h1>
      <Tabs defaultValue="users">
        <TabsList>
          <TabsTrigger value="users">İstifadəçi icazələri</TabsTrigger>
          <TabsTrigger value="roles">Rollar</TabsTrigger>
          <TabsTrigger value="perms">İcazələr</TabsTrigger>
        </TabsList>
        <TabsContent value="users"><UserPermissionsTab /></TabsContent>
        <TabsContent value="roles"><RolesTab /></TabsContent>
        <TabsContent value="perms"><PermissionsTab /></TabsContent>
      </Tabs>
    </div>
  )
}

function PermissionsTab() {
  const { data, isLoading, isError, error, refetch } = usePermissionCatalog()
  if (isLoading) return <LoadingState />
  if (isError) return <ErrorState error={error} onRetry={() => refetch()} />
  return (
    <div className="grid gap-4 md:grid-cols-2">
      {(data ?? []).map((g) => (
        <Card key={g.group}>
          <CardHeader className="pb-2"><CardTitle className="text-sm uppercase tracking-wide">{g.group}</CardTitle></CardHeader>
          <CardContent className="space-y-1">
            {g.permissions.map((p) => (
              <div key={p.key} className="flex items-center justify-between text-sm">
                <span>{p.label}</span>
                <code className="rounded bg-muted px-1 text-xs text-muted-foreground">{p.key}</code>
              </div>
            ))}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

function RolesTab() {
  const { data, isLoading, isError, error, refetch } = useRoles()
  const { data: catalog } = usePermissionCatalog()
  const update = useUpdateRole()
  const { toast } = useToast()
  const [editing, setEditing] = useState<RoleInfo | null>(null)
  const [selected, setSelected] = useState<Set<Permission>>(new Set())

  if (isLoading) return <LoadingState />
  if (isError) return <ErrorState error={error} onRetry={() => refetch()} />

  const startEdit = (r: RoleInfo) => { setEditing(r); setSelected(new Set(r.default_permissions)) }
  const toggle = (key: Permission) => setSelected((prev) => { const n = new Set(prev); if (n.has(key)) n.delete(key); else n.add(key); return n })
  const save = () => {
    if (!editing) return
    update.mutate({ role: editing.role, permissions: [...selected] }, {
      onSuccess: () => { toast({ variant: 'success', title: 'Rol şablonu yeniləndi' }); setEditing(null) },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <div className="space-y-3">
      {(data ?? []).map((r) => (
        <Card key={r.role}>
          <CardHeader className="flex flex-row items-start justify-between gap-3 space-y-0 pb-2">
            <div>
              <CardTitle className="text-base">{r.label} <span className="text-xs font-normal text-muted-foreground">({r.admin_count} admin)</span></CardTitle>
              <CardDescription>{r.description}</CardDescription>
            </div>
            {!r.is_super && (
              <Button size="sm" variant="outline" onClick={() => startEdit(r)}>Şablonu redaktə et</Button>
            )}
          </CardHeader>
          <CardContent>
            {editing?.role === r.role && catalog ? (
              <div className="space-y-3">
                {catalog.map((g) => (
                  <div key={g.group}>
                    <p className="mb-1 text-xs font-semibold uppercase text-muted-foreground">{g.group}</p>
                    <div className="flex flex-wrap gap-2">
                      {g.permissions.map((p) => (
                        <label key={p.key} className="flex items-center gap-1.5 rounded border px-2 py-1 text-xs">
                          <input type="checkbox" checked={selected.has(p.key)} onChange={() => toggle(p.key)} />
                          {p.key}
                        </label>
                      ))}
                    </div>
                  </div>
                ))}
                <div className="flex gap-2">
                  <Button size="sm" onClick={save} disabled={update.isPending}>{update.isPending && <Loader2 className="h-4 w-4 animate-spin" />}Yadda saxla</Button>
                  <Button size="sm" variant="outline" onClick={() => setEditing(null)}>İmtina</Button>
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">{r.is_super ? 'Bütün icazələr (dəyişdirilə bilməz).' : `${r.default_permissions.length} default icazə.`}</p>
            )}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

function UserPermissionsTab() {
  const { data: admins } = useAdmins()
  const { data: catalog } = usePermissionCatalog()
  const [adminId, setAdminId] = useState<number | null>(null)
  const { data: perms } = useUserPermissions(adminId)
  const grant = useGrantPermission(adminId ?? 0)
  const revoke = useRevokePermission(adminId ?? 0)
  const reset = useResetPermissions(adminId ?? 0)
  const { toast } = useToast()
  const err = (e: unknown) => toast({ variant: 'destructive', title: 'Xəta', description: e instanceof ApiError ? e.message : undefined })

  const inherited = new Set(perms?.inherited ?? [])
  const additional = new Set(perms?.additional ?? [])
  const revoked = new Set(perms?.revoked ?? [])
  const effective = new Set(perms?.effective ?? [])

  return (
    <div className="space-y-4">
      <Card>
        <CardContent className="flex flex-wrap items-center gap-3 pt-6">
          <span className="text-sm font-medium">Admin seç:</span>
          <select className="h-10 min-w-64 rounded-md border border-input bg-card px-3 text-sm"
            value={adminId ?? ''} onChange={(e) => setAdminId(e.target.value ? Number(e.target.value) : null)}>
            <option value="">— seçin —</option>
            {(admins ?? []).filter((a) => a.role !== 'super_admin').map((a) => (
              <option key={a.id} value={a.id}>{a.name} — {a.email} ({roleLabels[a.role]})</option>
            ))}
          </select>
          {perms && (
            <>
              <Badge variant="muted">Rol: {roleLabels[perms.admin.role]}</Badge>
              <Badge variant="success">Effektiv: {perms.effective.length}</Badge>
              <Button size="sm" variant="outline" className="ml-auto" disabled={reset.isPending}
                onClick={() => reset.mutate(undefined, { onSuccess: () => toast({ variant: 'success', title: 'Rol defaultlarına sıfırlandı' }), onError: err })}>
                <RotateCcw className="h-4 w-4" /> Defaultlara sıfırla
              </Button>
            </>
          )}
        </CardContent>
      </Card>

      {perms && catalog && (
        <div className="grid gap-4 md:grid-cols-2">
          {catalog.map((g) => (
            <Card key={g.group}>
              <CardHeader className="pb-2"><CardTitle className="text-sm uppercase tracking-wide">{g.group}</CardTitle></CardHeader>
              <CardContent className="space-y-1.5">
                {g.permissions.map((p) => {
                  const isEff = effective.has(p.key)
                  const state = revoked.has(p.key) ? 'revoked' : additional.has(p.key) ? 'granted' : inherited.has(p.key) ? 'inherited' : 'none'
                  return (
                    <div key={p.key} className="flex items-center justify-between gap-2 text-sm">
                      <span className="flex items-center gap-2">
                        <span className={isEff ? 'text-foreground' : 'text-muted-foreground line-through'}>{p.key}</span>
                        {state === 'granted' && <Badge variant="success" className="text-[10px]">+ verilib</Badge>}
                        {state === 'revoked' && <Badge variant="destructive" className="text-[10px]">− alınıb</Badge>}
                        {state === 'inherited' && <Badge variant="muted" className="text-[10px]">rol</Badge>}
                      </span>
                      <span className="flex gap-1">
                        {!isEff && <Button size="sm" variant="ghost" className="h-6 px-2 text-xs" disabled={grant.isPending}
                          onClick={() => grant.mutate(p.key, { onError: err })}>Ver</Button>}
                        {isEff && <Button size="sm" variant="ghost" className="h-6 px-2 text-xs text-destructive" disabled={revoke.isPending}
                          onClick={() => revoke.mutate(p.key, { onError: err })}>Al</Button>}
                      </span>
                    </div>
                  )
                })}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  )
}
