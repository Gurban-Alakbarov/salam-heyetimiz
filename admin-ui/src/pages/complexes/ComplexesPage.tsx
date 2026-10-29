import { useState } from 'react'
import { Link } from 'react-router-dom'
import { Building2, Plus } from 'lucide-react'
import { useComplexList } from '@/api/complexes'
import { PERM } from '@/auth/permissions'
import { PermissionGate } from '@/components/PermissionGate'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ComplexFormDialog } from './ComplexDialogs'

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

      <ComplexFormDialog open={createOpen} onOpenChange={setCreateOpen} />
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
