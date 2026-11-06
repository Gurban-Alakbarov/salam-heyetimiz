import { type FormEvent, useState } from 'react'
import { useMutation } from '@tanstack/react-query'
import { KeyRound, Loader2, LockKeyhole, LogOut } from 'lucide-react'
import { useNavigate } from 'react-router-dom'
import { useAuth } from '@/auth/useAuth'
import { api, ApiError } from '@/lib/api'
import { PageHeader } from '@/components/PageHeader'
import { Alert, AlertDescription } from '@/components/ui/alert'
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
import { useToast } from '@/components/ui/toast'
import { formatDateTime } from '@/lib/format'
import type { AdminRole, RecoveryCodesResult } from '@/types/api'

const roleLabels: Record<AdminRole, string> = {
  super_admin: 'Super Admin',
  technical: 'Texniki',
  operator: 'Operator',
  finance: 'Maliyyə',
  support: 'Dəstək',
  complex_manager: 'Kompleks Meneceri',
}

function InfoRow({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between border-b py-2.5 text-sm last:border-0">
      <span className="text-muted-foreground">{label}</span>
      <span className="font-medium">{value ?? '—'}</span>
    </div>
  )
}

export function AccountPage() {
  const { admin, logout } = useAuth()
  const navigate = useNavigate()
  const [dialogOpen, setDialogOpen] = useState(false)
  const [passwordOpen, setPasswordOpen] = useState(false)

  if (!admin) return null

  const handleLogout = async () => {
    await logout()
    navigate('/login')
  }

  return (
    <div className="max-w-2xl space-y-6">
      <PageHeader title="Hesab" description="Profil və təhlükəsizlik" />

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Profil</CardTitle>
        </CardHeader>
        <CardContent>
          <InfoRow label="Ad" value={admin.name} />
          <InfoRow label="E-poçt" value={admin.email} />
          <InfoRow label="Rol" value={<Badge variant="secondary">{roleLabels[admin.role]}</Badge>} />
          <InfoRow label="Telefon" value={admin.phone} />
          <InfoRow label="2FA" value={admin.is_2fa_enabled ? 'Aktiv' : 'Deaktiv'} />
          <InfoRow label="Son giriş" value={formatDateTime(admin.last_login_at)} />
        </CardContent>
      </Card>

      <Card>
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Təhlükəsizlik</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">Parolu dəyiş (bütün sessiyalar bağlanır, yenidən daxil olursunuz).</p>
            <Button variant="outline" onClick={() => setPasswordOpen(true)}>
              <LockKeyhole className="h-4 w-4" />
              Parolu dəyiş
            </Button>
          </div>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p className="text-sm text-muted-foreground">Bərpa kodlarını yenidən yarat (köhnələri etibarsız olur).</p>
            <Button variant="outline" onClick={() => setDialogOpen(true)}>
              <KeyRound className="h-4 w-4" />
              Bərpa kodları
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="flex items-center justify-between py-4">
          <span className="text-sm text-muted-foreground">Sessiyadan çıxış</span>
          <Button variant="destructive" onClick={handleLogout}>
            <LogOut className="h-4 w-4" />
            Çıxış
          </Button>
        </CardContent>
      </Card>

      <RecoveryCodesDialog open={dialogOpen} onOpenChange={setDialogOpen} />
      <ChangePasswordDialog open={passwordOpen} onOpenChange={setPasswordOpen} onChanged={handleLogout} />
    </div>
  )
}

function ChangePasswordDialog({ open, onOpenChange, onChanged }: { open: boolean; onOpenChange: (open: boolean) => void; onChanged: () => void }) {
  const { toast } = useToast()
  const [current, setCurrent] = useState('')
  const [password, setPassword] = useState('')
  const [confirmation, setConfirmation] = useState('')

  const mutation = useMutation({
    mutationFn: async () =>
      api.post('/admin/v1/auth/password', { current_password: current, password, password_confirmation: confirmation }),
    onSuccess: () => {
      toast({ variant: 'success', title: 'Parol dəyişdirildi', description: 'Yeni parolla daxil olun.' })
      onChanged()
    },
    onError: (err) =>
      toast({
        variant: 'destructive',
        title: 'Parol dəyişmədi',
        description: err instanceof ApiError ? (err.fieldError('current_password') ?? err.fieldError('password') ?? err.message) : undefined,
      }),
  })

  const close = (next: boolean) => {
    if (mutation.isPending) return
    onOpenChange(next)
    if (!next) {
      setCurrent('')
      setPassword('')
      setConfirmation('')
      mutation.reset()
    }
  }

  const mismatch = confirmation !== '' && confirmation !== password

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Parolu dəyiş</DialogTitle>
          <DialogDescription>Ən azı 12 simvol. Dəyişəndən sonra yenidən daxil olacaqsınız.</DialogDescription>
        </DialogHeader>
        <form
          onSubmit={(e: FormEvent) => {
            e.preventDefault()
            mutation.mutate()
          }}
          className="space-y-4"
        >
          <div className="space-y-2">
            <Label htmlFor="current-password">Cari parol</Label>
            <Input id="current-password" type="password" autoComplete="current-password" value={current} onChange={(e) => setCurrent(e.target.value)} autoFocus required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="new-password">Yeni parol</Label>
            <Input id="new-password" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} required />
          </div>
          <div className="space-y-2">
            <Label htmlFor="new-password-confirmation">Yeni parol (təkrar)</Label>
            <Input id="new-password-confirmation" type="password" autoComplete="new-password" value={confirmation} onChange={(e) => setConfirmation(e.target.value)} required />
            {mismatch && <p className="text-xs text-destructive">Parollar uyğun gəlmir.</p>}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => close(false)} disabled={mutation.isPending}>
              İmtina
            </Button>
            <Button type="submit" disabled={mutation.isPending || !current || password.length < 12 || password !== confirmation}>
              {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Dəyiş
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

function RecoveryCodesDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const { toast } = useToast()
  const [totp, setTotp] = useState('')
  const [codes, setCodes] = useState<string[] | null>(null)

  const mutation = useMutation({
    mutationFn: async (code: string) =>
      (await api.post<RecoveryCodesResult>('/admin/v1/auth/recovery-codes', { totp: code })).data,
    onSuccess: (data) => setCodes(data.codes),
    onError: (err) =>
      toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
  })

  const submit = (e: FormEvent) => {
    e.preventDefault()
    mutation.mutate(totp.trim())
  }

  const close = (next: boolean) => {
    if (mutation.isPending) return
    onOpenChange(next)
    if (!next) {
      setTotp('')
      setCodes(null)
      mutation.reset()
    }
  }

  return (
    <Dialog open={open} onOpenChange={close}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Bərpa kodları</DialogTitle>
          <DialogDescription>Təsdiq üçün TOTP kodunu daxil edin.</DialogDescription>
        </DialogHeader>
        {codes ? (
          <div className="space-y-3">
            <Alert variant="warning">
              <AlertDescription>Bu kodlar yalnız bir dəfə göstərilir. İndi təhlükəsiz saxlayın.</AlertDescription>
            </Alert>
            <div className="grid grid-cols-2 gap-2 rounded-md border bg-muted/40 p-3 font-mono text-sm">
              {codes.map((c) => (
                <span key={c}>{c}</span>
              ))}
            </div>
            <DialogFooter>
              <Button onClick={() => close(false)}>Bağla</Button>
            </DialogFooter>
          </div>
        ) : (
          <form onSubmit={submit} className="space-y-4">
            <div className="space-y-2">
              <Label htmlFor="totp">TOTP kodu</Label>
              <Input
                id="totp"
                inputMode="numeric"
                value={totp}
                onChange={(e) => setTotp(e.target.value)}
                placeholder="000000"
                autoFocus
                required
              />
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => close(false)} disabled={mutation.isPending}>
                İmtina
              </Button>
              <Button type="submit" disabled={mutation.isPending || totp.trim().length < 6}>
                {mutation.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
                Yarat
              </Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}
