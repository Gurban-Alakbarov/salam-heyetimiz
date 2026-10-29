import { type FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { Loader2, Pencil } from 'lucide-react'
import {
  useDecommissionDevice,
  useDisableDevice,
  useEnableDevice,
  useTransferDevice,
} from '@/api/devices'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { OwnerEmailField } from '@/components/OwnerEmailField'
import { PermissionGate } from '@/components/PermissionGate'
import { Button } from '@/components/ui/button'
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
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import type { DeviceAdminDetail } from '@/types/api'

export function DeviceActions({ device }: { device: DeviceAdminDetail }) {
  const { toast } = useToast()
  const enable = useEnableDevice(device.id)
  const disable = useDisableDevice(device.id)
  const transfer = useTransferDevice(device.id)
  const decommission = useDecommissionDevice(device.id)

  const [disableOpen, setDisableOpen] = useState(false)
  const [decommOpen, setDecommOpen] = useState(false)
  const [transferOpen, setTransferOpen] = useState(false)

  const notifyError = (err: unknown) =>
    toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })

  const isActive = device.status === 'active'
  const isDisabled = device.status === 'disabled'
  const isDecommissioned = device.status === 'decommissioned'

  return (
    <div className="flex flex-wrap gap-2">
      <PermissionGate anyOf={[PERM.devicesUpdate]}>
        {!isDecommissioned && (
          <Button variant="outline" asChild>
            <Link to={`/devices/${device.id}/edit`}>
              <Pencil className="h-4 w-4" />
              Redaktə
            </Link>
          </Button>
        )}
      </PermissionGate>

      <PermissionGate anyOf={[PERM.devicesDisable]}>
        {(isDisabled || device.status === 'suspended') && (
          <Button
            variant="outline"
            disabled={enable.isPending}
            onClick={() =>
              enable.mutate(undefined, {
                onSuccess: () => toast({ variant: 'success', title: 'Cihaz aktivləşdirildi' }),
                onError: notifyError,
              })
            }
          >
            {enable.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
            Aktivləşdir
          </Button>
        )}
        {isActive && (
          <Button variant="outline" onClick={() => setDisableOpen(true)}>
            Müvəqqəti söndür
          </Button>
        )}
      </PermissionGate>

      {/* Y6: a complex gate has no owner, so there is no ownership to transfer. */}
      <PermissionGate anyOf={[PERM.devicesTransfer]}>
        {!isDecommissioned && device.ownership_mode !== 'complex' && (
          <Button variant="outline" onClick={() => setTransferOpen(true)}>
            Sahibliyi köçür
          </Button>
        )}
      </PermissionGate>

      <PermissionGate anyOf={[PERM.devicesDecommission]}>
        {!isDecommissioned && (
          <Button variant="destructive" onClick={() => setDecommOpen(true)}>
            İstismardan çıxar
          </Button>
        )}
      </PermissionGate>

      <ConfirmDialog
        open={disableOpen}
        onOpenChange={setDisableOpen}
        title="Cihazı müvəqqəti söndür"
        description="Söndürülmüş cihazla heç kim qapını aça bilməz. Sonra «Aktivləşdir» ilə geri açmaq olar."
        confirmLabel="Söndür"
        confirmVariant="destructive"
        requireReason
        loading={disable.isPending}
        onConfirm={(reason) =>
          disable.mutate(reason, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Cihaz söndürüldü' })
              setDisableOpen(false)
            },
            onError: notifyError,
          })
        }
      />

      <ConfirmDialog
        open={decommOpen}
        onOpenChange={setDecommOpen}
        title="Cihazı istismardan çıxar"
        description="Bu, geri qaytarılmayan əməliyyatdır: cihaz həmişəlik bağlanır, bütün istifadəçiləri silinir və bu IMEI/SIM ilə yeni cihaz yaratmaq mümkün olmayacaq. Cihazı sadəcə dayandırmaq üçün «Müvəqqəti söndür»dən istifadə edin."
        confirmLabel="Həmişəlik çıxar"
        confirmVariant="destructive"
        requireReason
        loading={decommission.isPending}
        onConfirm={(reason) =>
          decommission.mutate(reason, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Cihaz istismardan çıxarıldı' })
              setDecommOpen(false)
            },
            onError: notifyError,
          })
        }
      />

      <TransferDialog
        open={transferOpen}
        onOpenChange={setTransferOpen}
        loading={transfer.isPending}
        onSubmit={(payload) =>
          transfer.mutate(payload, {
            onSuccess: () => {
              toast({ variant: 'success', title: 'Sahiblik köçürüldü' })
              setTransferOpen(false)
            },
            onError: notifyError,
          })
        }
      />
    </div>
  )
}

function TransferDialog({
  open,
  onOpenChange,
  loading,
  onSubmit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  loading: boolean
  onSubmit: (payload: {
    new_owner_phone: string
    new_owner_email?: string
    reason: string
    keep_existing_users: boolean
  }) => void
}) {
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [reason, setReason] = useState('')
  const [keep, setKeep] = useState(true)

  const submit = (e: FormEvent) => {
    e.preventDefault()
    onSubmit({
      new_owner_phone: phone.trim(),
      new_owner_email: email.trim() || undefined,
      reason: reason.trim(),
      keep_existing_users: keep,
    })
  }

  return (
    <Dialog
      open={open}
      onOpenChange={(next) => {
        if (loading) return
        onOpenChange(next)
        if (!next) {
          setPhone('')
          setEmail('')
          setReason('')
          setKeep(true)
        }
      }}
    >
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>Sahibliyi köçür</DialogTitle>
            <DialogDescription>Cihazı yeni sahibə təyin edin.</DialogDescription>
          </DialogHeader>
          <div className="space-y-4 py-4">
            <div className="space-y-2">
              <Label htmlFor="transfer-phone">Yeni sahibin telefonu</Label>
              <Input
                id="transfer-phone"
                value={phone}
                onChange={(e) => setPhone(e.target.value)}
                placeholder="+994XXXXXXXXX"
                required
              />
            </div>
            <OwnerEmailField id="transfer-email" value={email} onChange={setEmail} who="Yeni sahib" />
            <div className="space-y-2">
              <Label htmlFor="transfer-reason">Səbəb</Label>
              <Textarea id="transfer-reason" value={reason} onChange={(e) => setReason(e.target.value)} required />
            </div>
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                className="h-4 w-4 rounded border-input"
                checked={keep}
                onChange={(e) => setKeep(e.target.checked)}
              />
              Mövcud istifadəçiləri saxla
            </label>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={loading}>
              İmtina
            </Button>
            <Button type="submit" disabled={loading || !phone.trim() || !reason.trim()}>
              {loading && <Loader2 className="h-4 w-4 animate-spin" />}
              Köçür
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
