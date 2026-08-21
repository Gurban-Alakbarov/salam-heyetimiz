import { type FormEvent, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { Loader2, LogIn, Plus, UserMinus } from 'lucide-react'
import { useAdmins, useComplexes, useCreateAdmin, useDeactivateAdmin, type CreatedAdmin } from '@/api/admins'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PermissionGate } from '@/components/PermissionGate'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import type { AdminRole, AdminUser } from '@/types/api'

const roleLabels: Record<AdminRole, string> = {
  super_admin: 'Super Admin', technical: 'Texniki', operator: 'Operator',
  finance: 'Maliyyə', support: 'Dəstək', complex_manager: 'Kompleks Meneceri',
}

export function AdminsPage() {
  const { admin: me, hasPermission, impersonate } = useAuth()
  const navigate = useNavigate()
  const { toast } = useToast()
  const { data: admins, isLoading, isError, error, refetch } = useAdmins()
  const deactivate = useDeactivateAdmin()

  const [createOpen, setCreateOpen] = useState(false)
  const [removeTarget, setRemoveTarget] = useState<AdminUser | null>(null)
  const [impersonatingId, setImpersonatingId] = useState<number | null>(null)

  const notifyError = (err: unknown) =>
    toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })

  const onImpersonate = async (id: number) => {
    setImpersonatingId(id)
    try {
      await impersonate(id)
      navigate('/')
    } catch (err) {
      notifyError(err)
    } finally {
      setImpersonatingId(null)
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">Adminlər</h1>
        <PermissionGate anyOf={[PERM.adminsCreate]}>
          <Button onClick={() => setCreateOpen(true)}>
            <Plus className="h-4 w-4" />
            Admin yarat
          </Button>
        </PermissionGate>
      </div>

      <Card className="overflow-hidden">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Hesablar</CardTitle>
        </CardHeader>
        <CardContent className="px-0 pb-0">
          {isLoading ? (
            <div className="p-6"><LoadingState /></div>
          ) : isError ? (
            <div className="p-6"><ErrorState error={error} onRetry={() => refetch()} /></div>
          ) : (
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>E-poçt</TableHead>
                  <TableHead>Ad</TableHead>
                  <TableHead>Rol</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead className="w-0" />
                </TableRow>
              </TableHeader>
              <TableBody>
                {(admins ?? []).map((a) => (
                  <TableRow key={a.id}>
                    <TableCell className="font-medium">{a.email}</TableCell>
                    <TableCell>{a.name}</TableCell>
                    <TableCell>{roleLabels[a.role] ?? a.role}</TableCell>
                    <TableCell>
                      <Badge variant={a.status === 'active' ? 'success' : 'muted'}>{a.status}</Badge>
                    </TableCell>
                    <TableCell className="space-x-1 pr-4 text-right">
                      {hasPermission(PERM.adminsImpersonate) && a.id !== me?.id && a.status === 'active' && (
                        <Button size="sm" variant="outline" disabled={impersonatingId === a.id} onClick={() => onImpersonate(a.id)}>
                          {impersonatingId === a.id ? <Loader2 className="h-4 w-4 animate-spin" /> : <LogIn className="h-4 w-4" />}
                          Daxil ol
                        </Button>
                      )}
                      {a.id !== me?.id && a.status === 'active' && (
                        <PermissionGate anyOf={['admins.delete']}>
                          <Button size="icon" variant="ghost" aria-label="Deaktiv et" onClick={() => setRemoveTarget(a)}>
                            <UserMinus className="h-4 w-4 text-destructive" />
                          </Button>
                        </PermissionGate>
                      )}
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          )}
        </CardContent>
      </Card>

      <CreateAdminDialog open={createOpen} onOpenChange={setCreateOpen} />

      <ConfirmDialog
        open={removeTarget !== null}
        onOpenChange={(n) => { if (!n) setRemoveTarget(null) }}
        title="Admini deaktiv et"
        description={`${removeTarget?.email ?? ''} hesabı deaktiv ediləcək (offboarded).`}
        confirmLabel="Deaktiv et"
        confirmVariant="destructive"
        loading={deactivate.isPending}
        onConfirm={() => {
          if (!removeTarget) return
          deactivate.mutate(removeTarget.id, {
            onSuccess: () => { toast({ variant: 'success', title: 'Admin deaktiv edildi' }); setRemoveTarget(null) },
            onError: notifyError,
          })
        }}
      />
    </div>
  )
}

function CreateAdminDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const { toast } = useToast()
  const create = useCreateAdmin()
  const { data: complexes } = useComplexes()
  const [email, setEmail] = useState('')
  const [name, setName] = useState('')
  const [password, setPassword] = useState('')
  const [role, setRole] = useState<AdminRole>('technical')
  const [complexId, setComplexId] = useState<string>('')
  const [created, setCreated] = useState<CreatedAdmin | null>(null)

  const reset = () => { setEmail(''); setName(''); setPassword(''); setRole('technical'); setComplexId(''); setCreated(null) }

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate(
      { email: email.trim(), name: name.trim(), password, role, complex_id: role === 'complex_manager' && complexId ? Number(complexId) : null },
      {
        onSuccess: (res) => { setCreated(res); toast({ variant: 'success', title: 'Admin yaradıldı' }) },
        onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={(n) => { if (create.isPending) return; onOpenChange(n); if (!n) reset() }}>
      <DialogContent>
        {created ? (
          <div className="space-y-3">
            <DialogHeader>
              <DialogTitle>Admin yaradıldı — 2FA məlumatları</DialogTitle>
              <DialogDescription>Bu məlumatlar yalnız bir dəfə göstərilir. Yeni adminə təhvil verin.</DialogDescription>
            </DialogHeader>
            <div className="space-y-1 text-sm">
              <p><span className="text-muted-foreground">TOTP secret:</span> <code className="rounded bg-muted px-1">{created.totp_secret}</code></p>
              <p className="text-muted-foreground">Bərpa kodları:</p>
              <ul className="grid grid-cols-2 gap-1">
                {created.recovery_codes.map((c) => <li key={c}><code className="rounded bg-muted px-1">{c}</code></li>)}
              </ul>
            </div>
            <DialogFooter>
              <Button onClick={() => { onOpenChange(false); reset() }}>Bağla</Button>
            </DialogFooter>
          </div>
        ) : (
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>Yeni admin</DialogTitle>
              <DialogDescription>Admin hesabı yaradın və rol təyin edin.</DialogDescription>
            </DialogHeader>
            <div className="space-y-3 py-4">
              <div className="space-y-2">
                <Label htmlFor="a-email">E-poçt</Label>
                <Input id="a-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="a-name">Ad</Label>
                <Input id="a-name" value={name} onChange={(e) => setName(e.target.value)} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="a-pass">Parol (≥12 simvol)</Label>
                <Input id="a-pass" type="text" value={password} onChange={(e) => setPassword(e.target.value)} minLength={12} required />
              </div>
              <div className="space-y-2">
                <Label htmlFor="a-role">Rol</Label>
                <select id="a-role" className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm" value={role} onChange={(e) => setRole(e.target.value as AdminRole)}>
                  {(Object.keys(roleLabels) as AdminRole[]).map((r) => <option key={r} value={r}>{roleLabels[r]}</option>)}
                </select>
              </div>
              {role === 'complex_manager' && (
                <div className="space-y-2">
                  <Label htmlFor="a-complex">Kompleks</Label>
                  <select id="a-complex" className="h-10 w-full rounded-md border border-input bg-card px-3 text-sm" value={complexId} onChange={(e) => setComplexId(e.target.value)} required>
                    <option value="">Seçin…</option>
                    {(complexes ?? []).map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                  </select>
                </div>
              )}
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={create.isPending}>İmtina</Button>
              <Button type="submit" disabled={create.isPending || !email.trim() || !name.trim() || password.length < 12}>
                {create.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                Yarat
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}
