import { useState } from 'react'
import { Loader2, RotateCcw } from 'lucide-react'
import { useAdmins } from '@/api/admins'
import {
  useGrantPermission, usePermissionCatalog, useResetPermissions, useRevokePermission, useRoles, useUpdateRole,
  useUserPermissions, type RoleInfo,
} from '@/api/access'
import { ApiError } from '@/lib/api'
import { ConfirmDialog } from '@/components/ConfirmDialog'
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

/** Permission groups in words (the catalog sends the technical group code). */
const groupLabels: Record<string, string> = {
  dashboard: 'İdarə paneli', system: 'Sistem', admins: 'Adminlər', audit: 'Audit', access: 'İcazələr',
  complexes: 'Komplekslər', applications: 'Müraciətlər', devices: 'Cihazlar', whitelist: 'Whitelist (köhnə)',
  commands: 'Qapı açılışları və qonaq linkləri', residents: 'Sakinlər', apartments: 'Mənzillər', vehicles: 'Avtomobillər',
  finance: 'Maliyyə', support: 'Dəstək', notifications: 'Bildirişlər',
}
const groupLabel = (g: string) => groupLabels[g] ?? g

const NOT_IN_USE_HINT = 'Bu icazəni hələ heç bir funksiya yoxlamır — vermək və ya almaq indi heç nəyi dəyişmir'
const KOMENDANT_HINT = 'Kompleks meneceri yalnız öz kompleksini görür; bu icazə bütün komplekslərə aiddir və ona verilə bilməz'

export function AccessControlPage() {
  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Admin icazələri</h1>
        <p className="text-sm text-muted-foreground">Panel istifadəçilərinin (adminlərin) nə edə biləcəyi. Sakinlərin qapıya girişi buradan idarə olunmur.</p>
      </div>
      <Tabs defaultValue="users">
        <TabsList>
          <TabsTrigger value="users">Ayrı-ayrı adminlər</TabsTrigger>
          <TabsTrigger value="roles">Rol şablonları</TabsTrigger>
          <TabsTrigger value="perms">Bütün icazələr</TabsTrigger>
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
          <CardHeader className="pb-2"><CardTitle className="text-sm uppercase tracking-wide">{groupLabel(g.group)}</CardTitle></CardHeader>
          <CardContent className="space-y-1">
            {g.permissions.map((p) => (
              <div key={p.key} className="flex items-center justify-between text-sm">
                <span className={p.in_use === false ? 'flex items-center gap-2 text-muted-foreground' : undefined}>
                  {p.label}
                  {p.in_use === false && <Badge variant="muted" className="text-[10px]" title={NOT_IN_USE_HINT}>istifadə olunmur</Badge>}
                </span>
                <code className="rounded bg-muted px-1 text-[10px] text-muted-foreground" title="Texniki açar">{p.key}</code>
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
  const [confirmSave, setConfirmSave] = useState(false)

  if (isLoading) return <LoadingState />
  if (isError) return <ErrorState error={error} onRetry={() => refetch()} />

  const startEdit = (r: RoleInfo) => { setEditing(r); setSelected(new Set(r.default_permissions)) }
  const toggle = (key: Permission) => setSelected((prev) => { const n = new Set(prev); if (n.has(key)) n.delete(key); else n.add(key); return n })
  const save = () => {
    if (!editing) return
    update.mutate({ role: editing.role, permissions: [...selected] }, {
      onSuccess: () => { toast({ variant: 'success', title: 'Rol şablonu yeniləndi' }); setEditing(null); setConfirmSave(false) },
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
                    <p className="mb-1 text-xs font-semibold uppercase text-muted-foreground">{groupLabel(g.group)}</p>
                    <div className="flex flex-wrap gap-2">
                      {g.permissions.map((p) => {
                        const blocked = !!r.grantable && !r.grantable.includes(p.key)
                        return (
                          <label key={p.key} title={blocked ? KOMENDANT_HINT : p.key}
                            className={`flex items-center gap-1.5 rounded border px-2 py-1 text-xs ${blocked ? 'cursor-not-allowed opacity-50' : ''}`}>
                            <input type="checkbox" disabled={blocked} checked={selected.has(p.key)} onChange={() => toggle(p.key)} />
                            {p.label}
                            {p.in_use === false && <Badge variant="muted" className="text-[10px]" title={NOT_IN_USE_HINT}>istifadə olunmur</Badge>}
                          </label>
                        )
                      })}
                    </div>
                  </div>
                ))}
                <div className="flex gap-2">
                  <Button size="sm" onClick={() => setConfirmSave(true)} disabled={update.isPending}>{update.isPending && <Loader2 className="h-4 w-4 animate-spin" />}Yadda saxla</Button>
                  <Button size="sm" variant="outline" onClick={() => setEditing(null)}>İmtina</Button>
                </div>
              </div>
            ) : (
              <p className="text-sm text-muted-foreground">{r.is_super ? 'Bütün icazələr (dəyişdirilə bilməz).' : `${r.default_permissions.length} standart icazə.`}</p>
            )}
          </CardContent>
        </Card>
      ))}
      <ConfirmDialog
        open={confirmSave}
        onOpenChange={setConfirmSave}
        title="Rol şablonu dəyişdirilsin?"
        description={`Bu dəyişiklik «${editing?.label ?? ''}» rolundakı bütün adminlərə dərhal təsir edir.`}
        confirmLabel="Yadda saxla"
        loading={update.isPending}
        onConfirm={save}
      />
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
  const [confirmReset, setConfirmReset] = useState(false)

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
              <Button size="sm" variant="outline" className="ml-auto" disabled={reset.isPending} onClick={() => setConfirmReset(true)}>
                <RotateCcw className="h-4 w-4" /> Rolun standart icazələrinə qaytar
              </Button>
            </>
          )}
        </CardContent>
      </Card>

      {perms && catalog && (
        <div className="grid gap-4 md:grid-cols-2">
          {catalog.map((g) => (
            <Card key={g.group}>
              <CardHeader className="pb-2"><CardTitle className="text-sm uppercase tracking-wide">{groupLabel(g.group)}</CardTitle></CardHeader>
              <CardContent className="space-y-1.5">
                {g.permissions.map((p) => {
                  const isEff = effective.has(p.key)
                  const state = revoked.has(p.key) ? 'revoked' : additional.has(p.key) ? 'granted' : inherited.has(p.key) ? 'inherited' : 'none'
                  const blocked = !!perms.grantable && !perms.grantable.includes(p.key)
                  return (
                    <div key={p.key} className="flex items-center justify-between gap-2 text-sm">
                      <span className="flex items-center gap-2">
                        <span title={p.key} className={isEff ? 'text-foreground' : 'text-muted-foreground line-through'}>{p.label}</span>
                        {state === 'granted' && <Badge variant="success" className="text-[10px]">+ verilib</Badge>}
                        {state === 'revoked' && <Badge variant="destructive" className="text-[10px]">− alınıb</Badge>}
                        {state === 'inherited' && <Badge variant="muted" className="text-[10px]">rol</Badge>}
                        {p.in_use === false && <Badge variant="muted" className="text-[10px]" title={NOT_IN_USE_HINT}>istifadə olunmur</Badge>}
                        {blocked && !isEff && <Badge variant="muted" className="text-[10px]" title={KOMENDANT_HINT}>komendanta verilmir</Badge>}
                      </span>
                      <span className="flex gap-1">
                        {!isEff && !blocked && <Button size="sm" variant="ghost" className="h-6 px-2 text-xs" disabled={grant.isPending}
                          onClick={() => grant.mutate(p.key, { onSuccess: () => toast({ variant: 'success', title: `Verildi: ${p.label}` }), onError: err })}>Ver</Button>}
                        {isEff && <Button size="sm" variant="ghost" className="h-6 px-2 text-xs text-destructive" disabled={revoke.isPending}
                          onClick={() => revoke.mutate(p.key, { onSuccess: () => toast({ variant: 'success', title: `Alındı: ${p.label}` }), onError: err })}>Al</Button>}
                      </span>
                    </div>
                  )
                })}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
      <ConfirmDialog
        open={confirmReset}
        onOpenChange={setConfirmReset}
        title="Əlavə verilən və alınan icazələr silinsin?"
        description="Bu admin yalnız rolunun standart icazələri ilə qalacaq."
        confirmLabel="Qaytar"
        loading={reset.isPending}
        onConfirm={() => reset.mutate(undefined, {
          onSuccess: () => { toast({ variant: 'success', title: 'Rolun standart icazələrinə qaytarıldı' }); setConfirmReset(false) },
          onError: err,
        })}
      />
    </div>
  )
}
