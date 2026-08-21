import { type FormEvent, useState } from 'react'
import { BadgeCheck, Loader2, Trash2, UserCog, UserPlus } from 'lucide-react'
import { useAddDeviceUser, useAssignDevice, useGrantSubscription, useRemoveDeviceUser } from '@/api/devices'
import { ApiError } from '@/lib/api'
import { PERM } from '@/auth/permissions'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { useToast } from '@/components/ui/toast'
import { formatDateTime } from '@/lib/format'
import type { DeviceAdminDetail, DeviceUser, DeviceUserRole, DeviceUserStatus } from '@/types/api'

const rosterRoleLabels: Record<DeviceUserRole, string> = { owner: 'Sahib', user: 'İstifadəçi' }
const rosterStatusLabels: Record<DeviceUserStatus, string> = { active: 'Aktiv', revoked: 'Ləğv edilib' }

export function RosterSection({ device }: { device: DeviceAdminDetail }) {
  const { toast } = useToast()
  const assign = useAssignDevice(device.id)
  const addUser = useAddDeviceUser(device.id)
  const removeUser = useRemoveDeviceUser(device.id)
  const grant = useGrantSubscription(device.id)

  const [assignOpen, setAssignOpen] = useState(false)
  const [addOpen, setAddOpen] = useState(false)
  const [removeTarget, setRemoveTarget] = useState<DeviceUser | null>(null)
  const [grantTarget, setGrantTarget] = useState<DeviceUser | null>(null)

  const hasOwner = device.owner !== null
  const notifyError = (err: unknown) =>
    toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })

  return (
    <Card className="overflow-hidden">
      <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0 pb-2">
        <CardTitle className="text-base">İstifadəçilər ({device.users.length})</CardTitle>
        {hasOwner ? (
          <PermissionGate anyOf={[PERM.residentsCreate]}>
            <Button size="sm" variant="outline" onClick={() => setAddOpen(true)}>
              <UserPlus className="h-4 w-4" />
              Sakin əlavə et
            </Button>
          </PermissionGate>
        ) : (
          <PermissionGate anyOf={[PERM.devicesAssign]}>
            <Button size="sm" onClick={() => setAssignOpen(true)}>
              <UserCog className="h-4 w-4" />
              Sahib təyin et
            </Button>
          </PermissionGate>
        )}
      </CardHeader>
      <CardContent className="px-0 pb-0">
        {device.users.length === 0 ? (
          <p className="px-6 pb-6 text-sm text-muted-foreground">
            {hasOwner ? 'İstifadəçi yoxdur.' : 'Cihaz hələ heç bir sakinə təyin edilməyib.'}
          </p>
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Telefon</TableHead>
                <TableHead>E-poçt</TableHead>
                <TableHead>Rol</TableHead>
                <TableHead>Status</TableHead>
                <TableHead>Abunəlik</TableHead>
                <TableHead>Son açılış</TableHead>
                <TableHead className="w-0" />
              </TableRow>
            </TableHeader>
            <TableBody>
              {device.users.map((u) => (
                <TableRow key={u.id}>
                  <TableCell className="font-medium">
                    <div className="flex items-center gap-2">
                      <span>{u.user?.phone_masked ?? '—'}</span>
                      {/* The account was deleted from the system; the revoked row stays for history. */}
                      {u.user?.deleted && (
                        <Badge variant="destructive" className="text-[10px]">Silinib</Badge>
                      )}
                    </div>
                  </TableCell>
                  <TableCell className="text-muted-foreground">
                    {u.user?.email ? (
                      <span className="select-all">{u.user.email}</span>
                    ) : (
                      <span className="text-xs">—</span>
                    )}
                  </TableCell>
                  <TableCell>{rosterRoleLabels[u.role] ?? u.role}</TableCell>
                  <TableCell>
                    <Badge variant={u.status === 'active' ? 'success' : 'muted'}>
                      {rosterStatusLabels[u.status] ?? u.status}
                    </Badge>
                  </TableCell>
                  <TableCell>
                    {u.subscription ? (
                      <StatusBadge kind="subscription" value={u.subscription.status} />
                    ) : (
                      <span className="text-xs text-muted-foreground">—</span>
                    )}
                  </TableCell>
                  <TableCell className="text-muted-foreground">{formatDateTime(u.last_open_at)}</TableCell>
                  <TableCell className="pr-4 text-right">
                    {/* Actions need a live account — a deleted/revoked member has nothing to act on. */}
                    <div className="flex justify-end gap-1">
                      {u.status === 'active' && u.user !== null && (
                        <PermissionGate anyOf={[PERM.subscriptionsManage]}>
                          <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Abunəliyi aktivləşdir"
                            title="Abunəliyi aktivləşdir (ödənişsiz)"
                            onClick={() => setGrantTarget(u)}
                          >
                            <BadgeCheck className="h-4 w-4 text-emerald-600" />
                          </Button>
                        </PermissionGate>
                      )}
                      {u.role !== 'owner' && u.status === 'active' && u.user !== null && (
                        <PermissionGate anyOf={[PERM.residentsDelete]}>
                          <Button
                            size="icon"
                            variant="ghost"
                            aria-label="Sakini çıxar"
                            title="Cihazdan çıxar"
                            onClick={() => setRemoveTarget(u)}
                          >
                            <Trash2 className="h-4 w-4 text-destructive" />
                          </Button>
                        </PermissionGate>
                      )}
                    </div>
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </CardContent>

      <AssignOwnerDialog
        open={assignOpen}
        onOpenChange={setAssignOpen}
        loading={assign.isPending}
        onSubmit={(payload) =>
          assign.mutate(payload, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Sahib təyin edildi' })
              setAssignOpen(false)
            },
            onError: notifyError,
          })
        }
      />

      <AddResidentDialog
        open={addOpen}
        onOpenChange={setAddOpen}
        loading={addUser.isPending}
        onSubmit={(phone) =>
          addUser.mutate(phone, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Sakin əlavə edildi' })
              setAddOpen(false)
            },
            onError: notifyError,
          })
        }
      />

      <ConfirmDialog
        open={removeTarget !== null}
        onOpenChange={(next) => {
          if (!next) setRemoveTarget(null)
        }}
        title="Sakini çıxar"
        description={`${removeTarget?.user?.phone_masked ?? ''} — cihazın istifadəçiləri siyahısından çıxarılacaq və whitelist-dən silinəcək.`}
        confirmLabel="Çıxar"
        confirmVariant="destructive"
        loading={removeUser.isPending}
        onConfirm={() => {
          if (!removeTarget?.user) return
          removeUser.mutate(removeTarget.user.id, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Sakin çıxarıldı' })
              setRemoveTarget(null)
            },
            onError: notifyError,
          })
        }}
      />

      <GrantSubscriptionDialog
        member={grantTarget}
        onOpenChange={(next) => {
          if (!next) setGrantTarget(null)
        }}
        loading={grant.isPending}
        onSubmit={(termDays) => {
          if (!grantTarget?.user) return
          grant.mutate(
            {
              userId: grantTarget.user.id,
              term_days: termDays,
              tier: grantTarget.role === 'owner' ? 'main' : 'additional',
            },
            {
              onSuccess: () => {
                toast({ variant: 'success', title: 'Abunəlik aktivləşdirildi' })
                setGrantTarget(null)
              },
              onError: notifyError,
            },
          )
        }}
      />
    </Card>
  )
}

/**
 * Activate/extend a resident's entitlement WITHOUT a payment. No order is created — Payments still
 * owns real money; this only grants time. Granting on a live subscription extends it from its end.
 */
function GrantSubscriptionDialog({
  member,
  onOpenChange,
  loading,
  onSubmit,
}: {
  member: DeviceUser | null
  onOpenChange: (open: boolean) => void
  loading: boolean
  onSubmit: (termDays: number) => void
}) {
  const [days, setDays] = useState('365')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    const parsed = Number.parseInt(days, 10)
    onSubmit(Number.isFinite(parsed) && parsed > 0 ? parsed : 365)
  }

  const isExtension = member?.subscription?.status === 'active'

  return (
    <Dialog
      open={member !== null}
      onOpenChange={(next) => {
        if (loading) return
        onOpenChange(next)
        if (!next) setDays('365')
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{isExtension ? 'Abunəliyi uzat' : 'Abunəliyi aktivləşdir'}</DialogTitle>
            <DialogDescription>
              {member?.user?.email ?? member?.user?.phone_masked} üçün abunəlik{' '}
              <strong>ödəniş olmadan</strong> {isExtension ? 'uzadılacaq' : 'aktivləşdiriləcək'}. Sifariş
              yaradılmır, məbləğ tutulmur — yalnız istifadə hüququ verilir və sakin cihazı aça bilər.
              {isExtension && ' Mövcud müddətin sonundan əlavə olunur.'}
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2 py-4">
            <Label htmlFor="grant-days">Müddət (gün)</Label>
            <Input
              id="grant-days"
              type="number"
              min={1}
              max={3650}
              value={days}
              onChange={(e) => setDays(e.target.value)}
              required
            />
            <p className="text-xs text-muted-foreground">Default: 365 gün (1 il).</p>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
              İmtina
            </Button>
            <Button type="submit" disabled={loading}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              {isExtension ? 'Uzat' : 'Aktivləşdir'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function AssignOwnerDialog({
  open,
  onOpenChange,
  loading,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  loading: boolean
  onSubmit: (payload: { owner_phone: string; location_label?: string }) => void
}) {
  const [phone, setPhone] = useState('')
  const [label, setLabel] = useState('')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    onSubmit({ owner_phone: phone.trim(), location_label: label.trim() || undefined })
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (loading) return
        onOpenChange(next)
        if (!next) {
          setPhone('')
          setLabel('')
        }
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>Sahib təyin et</DialogTitle>
            <DialogDescription>
              Cihazı (barrieri) sakinə bağlayın. Nömrə üzrə istifadəçi yoxdursa, avtomatik yaradılır.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="assign-phone">Sahibin telefonu</Label>
              <Input
                id="assign-phone"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+994XXXXXXXXX"
                required
              />
            </div>
            <div className="space-y-2">
              <Label htmlFor="assign-label">Ünvan / yer (istəyə bağlı)</Label>
              <Input
                id="assign-label"
                value={label}
                onChange={(e) => setLabel(e.target.value)}
                placeholder="Blok A, giriş"
              />
            </div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
              İmtina
            </Button>
            <Button type="submit" disabled={loading || !phone.trim()}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Təyin et
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function AddResidentDialog({
  open,
  onOpenChange,
  loading,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  loading: boolean
  onSubmit: (phone: string) => void
}) {
  const [phone, setPhone] = useState('')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    onSubmit(phone.trim())
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (loading) return
        onOpenChange(next)
        if (!next) setPhone('')
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>Sakin əlavə et</DialogTitle>
            <DialogDescription>
              Sakini telefon nömrəsi ilə cihazın istifadəçilərinə əlavə edin. Nömrə üzrə istifadəçi yoxdursa,
              avtomatik yaradılır və whitelist-ə əlavə olunur.
            </DialogDescription>
          </DialogHeader>
          <div className="space-y-2 py-4">
            <Label htmlFor="member-phone">Sakinin telefonu</Label>
            <Input
              id="member-phone"
              value={phone}
              onChange={(e) => setPhone(e.target.value)}
              placeholder="+994XXXXXXXXX"
              required
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
              İmtina
            </Button>
            <Button type="submit" disabled={loading || !phone.trim()}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Əlavə et
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
