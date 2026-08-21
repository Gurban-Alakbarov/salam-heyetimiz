import { type FormEvent, useState } from 'react'
import { Link } from 'react-router-dom'
import { Building2, Loader2, Plus } from 'lucide-react'
import { useComplexList, useCreateComplex } from '@/api/complexes'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
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
import { useToast } from '@/components/ui/toast'

export function ComplexesPage() {
  const { data, isLoading, isError, error, refetch } = useComplexList()
  const [createOpen, setCreateOpen] = useState(false)

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <h1 className="text-2xl font-semibold tracking-tight">Komplekslər</h1>
        <PermissionGate anyOf={[PERM.complexesManage]}>
          <Button onClick={() => setCreateOpen(true)}><Plus className="h-4 w-4" /> Kompleks yarat</Button>
        </PermissionGate>
      </div>

      {isLoading ? (
        <LoadingState />
      ) : isError ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {(data ?? []).map((c) => (
            <Link key={c.id} to={`/complexes/${c.id}`}>
              <Card className="h-full transition-colors hover:border-primary/50">
                <CardHeader className="pb-2">
                  <CardTitle className="flex items-center gap-2 text-base">
                    <Building2 className="h-4 w-4 text-muted-foreground" /> {c.name}
                    {!c.is_active && <Badge variant="muted">Deaktiv</Badge>}
                  </CardTitle>
                  <code className="text-xs text-muted-foreground">{c.code}</code>
                </CardHeader>
                <CardContent className="grid grid-cols-2 gap-2 text-sm">
                  <Stat label="Cihaz" value={`${c.stats.devices_online}/${c.stats.devices} onlayn`} />
                  <Stat label="Sakin" value={c.stats.residents} />
                  <Stat label="Menecer" value={c.stats.managers} />
                  <Stat label="Ünvan" value={c.address ?? '—'} />
                </CardContent>
              </Card>
            </Link>
          ))}
          {(data ?? []).length === 0 && <p className="text-sm text-muted-foreground">Kompleks yoxdur.</p>}
        </div>
      )}

      <CreateComplexDialog open={createOpen} onOpenChange={setCreateOpen} />
    </div>
  )
}

function Stat({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex flex-col">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="truncate">{value}</span>
    </div>
  )
}

function CreateComplexDialog({ open, onOpenChange }: { open: boolean; onOpenChange: (o: boolean) => void }) {
  const { toast } = useToast()
  const create = useCreateComplex()
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [address, setAddress] = useState('')

  const submit = (e: FormEvent) => {
    e.preventDefault()
    create.mutate(
      { name: name.trim(), code: code.trim(), address: address.trim() || undefined },
      {
        onSuccess: () => { toast({ variant: 'success', title: 'Kompleks yaradıldı' }); onOpenChange(false); setName(''); setCode(''); setAddress('') },
        onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
      },
    )
  }

  return (
    <Dialog open={open} onOpenChange={(n) => { if (!create.isPending) onOpenChange(n) }}>
      <DialogContent>
        <form onSubmit={submit}>
          <DialogHeader>
            <DialogTitle>Yeni kompleks</DialogTitle>
            <DialogDescription>Yaşayış kompleksi yaradın. Cihazlar və sakinlər bu komplekslə əlaqələndirilir.</DialogDescription>
          </DialogHeader>
          <div className="space-y-3 py-4">
            <div className="space-y-2"><Label htmlFor="cx-name">Ad</Label><Input id="cx-name" value={name} onChange={(e) => setName(e.target.value)} required /></div>
            <div className="space-y-2"><Label htmlFor="cx-code">Kod</Label><Input id="cx-code" value={code} onChange={(e) => setCode(e.target.value)} placeholder="SEA-BREEZE" required /></div>
            <div className="space-y-2"><Label htmlFor="cx-addr">Ünvan (istəyə bağlı)</Label><Input id="cx-addr" value={address} onChange={(e) => setAddress(e.target.value)} /></div>
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={create.isPending}>İmtina</Button>
            <Button type="submit" disabled={create.isPending || !name.trim() || !code.trim()}>{create.isPending && <Loader2 className="h-4 w-4 animate-spin" />}Yarat</Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
