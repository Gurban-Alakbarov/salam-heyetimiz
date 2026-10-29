import { type FormEvent, useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import { type CreatedAdmin, useCreateAdmin, useLinkMobileUser } from '@/api/admins'
import { useCreateComplex, useUpdateComplex } from '@/api/complexes'
import { ApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { useToast } from '@/components/ui/toast'

export interface ComplexFormValue {
  id?: number
  name: string
  code?: string
  address: string | null
  latitude: number | null
  longitude: number | null
  is_active?: boolean
}

/**
 * Create or edit a complex: name, address, map coordinates (directions + the map on the complex page) and, when
 * editing, whether it is active. The code is set once on create.
 */
export function ComplexFormDialog({ open, onOpenChange, complex }: { open: boolean; onOpenChange: (o: boolean) => void; complex?: ComplexFormValue }) {
  const { toast } = useToast()
  const isEdit = !!complex?.id
  const create = useCreateComplex()
  const update = useUpdateComplex(complex?.id ?? 0)
  const pending = create.isPending || update.isPending
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [address, setAddress] = useState('')
  const [lat, setLat] = useState('')
  const [lng, setLng] = useState('')
  const [active, setActive] = useState(true)

  useEffect(() => {
    if (!open) return
    setName(complex?.name ?? '')
    setCode(complex?.code ?? '')
    setAddress(complex?.address ?? '')
    setLat(complex?.latitude != null ? String(complex.latitude) : '')
    setLng(complex?.longitude != null ? String(complex.longitude) : '')
    setActive(complex?.is_active ?? true)
  }, [open, complex])

  const fail = (err: unknown) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })
  const submit = (e: FormEvent) => {
    e.preventDefault()
    const body = {
      name: name.trim(),
      address: address.trim() || null,
      latitude: lat ? Number(lat) : null,
      longitude: lng ? Number(lng) : null,
    }
    if (isEdit) {
      update.mutate({ ...body, is_active: active }, { onSuccess: () => { toast({ variant: 'success', title: 'Kompleks yeniləndi' }); onOpenChange(false) }, onError: fail })
    } else {
      create.mutate({ ...body, code: code.trim() }, { onSuccess: () => { toast({ variant: 'success', title: 'Kompleks yaradıldı' }); onOpenChange(false) }, onError: fail })
    }
  }

  return (
    <Dialog open={open} onOpenChange={(n) => { if (!pending) onOpenChange(n) }}>
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>{isEdit ? 'Kompleksi redaktə et' : 'Yeni kompleks'}</DialogTitle>
            <DialogDescription>Cihazlar və sakinlər kompleksə bağlanır. Koordinatlar xəritə və «Yol göstər» üçündür.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-4">
            <div className="space-y-2"><Label htmlFor="cx-name">Ad</Label><Input id="cx-name" value={name} onChange={(e) => setName(e.target.value)} required /></div>
            {!isEdit && (
              <div className="space-y-2"><Label htmlFor="cx-code">Qısa kod</Label><Input id="cx-code" value={code} onChange={(e) => setCode(e.target.value)} placeholder="SEA-BREEZE" required />
                <p className="text-xs text-muted-foreground">Latın hərfləri, sonra dəyişdirilmir.</p></div>
            )}
            <div className="space-y-2"><Label htmlFor="cx-addr">Ünvan</Label><Input id="cx-addr" value={address} onChange={(e) => setAddress(e.target.value)} /></div>
            <div className="grid grid-cols-2 gap-3">
              <div className="space-y-2"><Label htmlFor="cx-lat">Enlik</Label><Input id="cx-lat" type="number" step="any" value={lat} onChange={(e) => setLat(e.target.value)} placeholder="40.4093" /></div>
              <div className="space-y-2"><Label htmlFor="cx-lng">Uzunluq</Label><Input id="cx-lng" type="number" step="any" value={lng} onChange={(e) => setLng(e.target.value)} placeholder="49.8671" /></div>
            </div>
            {isEdit && (
              <label className="flex items-start gap-2 text-sm">
                <input type="checkbox" className="mt-1" checked={active} onChange={(e) => setActive(e.target.checked)} />
                <span>Kompleks aktivdir<span className="block text-xs text-muted-foreground">Deaktiv kompleksə yeni sakin dəvət etmək olmur.</span></span>
              </label>
            )}
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>İmtina</Button>
            <Button type="submit" disabled={pending || !name.trim() || (!isEdit && !code.trim())}>{pending && <Loader2 className="h-4 w-4 animate-spin" />}{isEdit ? 'Yadda saxla' : 'Yarat'}</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}

/** A random 16-character password suggestion (the new komendant changes nothing — 2FA protects the account). */
function suggestPassword(): string {
  const chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789'
  const bytes = new Uint32Array(16)
  crypto.getRandomValues(bytes)
  return Array.from(bytes, (b) => chars[b % chars.length]).join('')
}

/**
 * Komendant for this complex in one step: create the admin (role Kompleks Meneceri, this complex) and — optionally —
 * link the mobile account they already registered in the app, so they can also manage residents from the phone.
 */
export function KomendantSetupDialog({ open, onOpenChange, complexId, complexName }: { open: boolean; onOpenChange: (o: boolean) => void; complexId: number; complexName: string }) {
  const { toast } = useToast()
  const create = useCreateAdmin()
  const link = useLinkMobileUser()
  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [mobileEmail, setMobileEmail] = useState('')
  const [created, setCreated] = useState<CreatedAdmin | null>(null)
  const [linkNote, setLinkNote] = useState<string | null>(null)

  useEffect(() => {
    if (open) { setName(''); setEmail(''); setPassword(suggestPassword()); setMobileEmail(''); setCreated(null); setLinkNote(null) }
  }, [open])

  const pending = create.isPending || link.isPending
  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate({ name: name.trim(), email: email.trim(), password, role: 'complex_manager', complex_id: complexId }, {
      onSuccess: (res) => {
        setCreated(res)
        if (mobileEmail.trim()) {
          link.mutate({ adminId: res.admin.id, email: mobileEmail.trim() }, {
            onSuccess: () => setLinkNote('Mobil hesab əlaqələndirildi — komendant tətbiqdə də sakinləri idarə edə bilər.'),
            onError: (err) => setLinkNote(`Mobil hesab əlaqələndirilmədi: ${err instanceof ApiError ? err.message : 'xəta'}. Sonra «Adminlər» səhifəsindən edə bilərsiniz.`),
          })
        }
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <Dialog open={open} onOpenChange={(n) => { if (!pending) onOpenChange(n) }}>
      <DialogContent>
        {created ? (
          <div className="space-y-3">
            <DialogHeader>
              <DialogTitle>Komendant yaradıldı</DialogTitle>
              <DialogDescription>Bu məlumatları komendanta təhvil verin — yalnız indi göstərilir.</DialogDescription>
            </DialogHeader>
            <div className="space-y-1 text-sm">
              <p><span className="text-muted-foreground">Panel girişi:</span> {created.admin.email}</p>
              <p><span className="text-muted-foreground">Parol:</span> <code className="rounded bg-muted px-1">{password}</code></p>
              <p><span className="text-muted-foreground">2FA (Google Authenticator) açarı:</span> <code className="rounded bg-muted px-1">{created.totp_secret}</code></p>
              <p className="text-muted-foreground">Bərpa kodları:</p>
              <ul className="grid grid-cols-2 gap-1">{created.recovery_codes.map((c) => <li key={c}><code className="rounded bg-muted px-1">{c}</code></li>)}</ul>
              {link.isPending && <p className="flex items-center gap-2 text-muted-foreground"><Loader2 className="h-4 w-4 animate-spin" /> Mobil hesab əlaqələndirilir…</p>}
              {linkNote && <p className="pt-1">{linkNote}</p>}
            </div>
            <DialogFooter><Button onClick={() => onOpenChange(false)} disabled={pending}>Hazırdır</Button></DialogFooter>
          </div>
        ) : (
          <form onSubmit={submit}>
            <DialogHeader>
              <DialogTitle>«{complexName}» üçün komendant</DialogTitle>
              <DialogDescription>Komendant yalnız bu kompleksi görür: sakinlər, cihazlar, qonaq linkləri, bildirişlər.</DialogDescription>
            </DialogHeader>
            <div className="space-y-3 py-4">
              <div className="space-y-2"><Label htmlFor="k-name">Ad, soyad</Label><Input id="k-name" value={name} onChange={(e) => setName(e.target.value)} required /></div>
              <div className="space-y-2"><Label htmlFor="k-email">Panelə giriş e-poçtu</Label><Input id="k-email" type="email" value={email} onChange={(e) => setEmail(e.target.value)} required /></div>
              <div className="space-y-2"><Label htmlFor="k-pass">Parol (təklif olunan)</Label><Input id="k-pass" type="password" autoComplete="new-password" value={password} onChange={(e) => setPassword(e.target.value)} minLength={12} required />
                <p className="text-xs text-muted-foreground">Yaradıldıqdan sonra bir dəfə göstəriləcək.</p></div>
              <div className="space-y-2"><Label htmlFor="k-mobile">Mobil tətbiqdəki e-poçtu (könüllü)</Label><Input id="k-mobile" type="email" value={mobileEmail} onChange={(e) => setMobileEmail(e.target.value)} />
                <p className="text-xs text-muted-foreground">Komendant tətbiqdə artıq qeydiyyatdan keçibsə, onun hesabı bura bağlanır.</p></div>
            </div>
            <DialogFooter>
              <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={pending}>İmtina</Button>
              <Button type="submit" disabled={pending || !name.trim() || !email.trim() || password.length < 12}>{pending && <Loader2 className="h-4 w-4 animate-spin" />} Yarat</Button>
            </DialogFooter>
          </form>
        )}
      </DialogContent>
    </Dialog>
  )
}
