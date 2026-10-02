import { useState } from 'react'
import { Link } from 'react-router-dom'
import { type AccountType, useMobileUsers } from '@/api/users'
import { formatDate } from '@/lib/format'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'

const typeLabel: Record<AccountType, string> = { physical: 'Fiziki', legal: 'Hüquqi' }

/** Mobile users directory (B11) — account type, verification, Komendant link, complex membership. */
export function UsersPage() {
  const [q, setQ] = useState('')
  const [type, setType] = useState<AccountType | 'none' | ''>('')
  const [page, setPage] = useState(1)
  const { data, isLoading, isError, error, refetch } = useMobileUsers({ q, account_type: type, page })
  const pages = data ? Math.max(1, Math.ceil(data.meta.total / data.meta.per_page)) : 1

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold tracking-tight">İstifadəçilər</h1>
      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-2 p-3">
          <Input placeholder="Ad, e-poçt, telefon…" value={q} onChange={(e) => { setQ(e.target.value); setPage(1) }} className="max-w-xs" />
          <select aria-label="Hesab növü" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={type}
            onChange={(e) => { setType(e.target.value as AccountType | 'none' | ''); setPage(1) }}>
            <option value="">Bütün növlər</option>
            <option value="physical">Fiziki</option>
            <option value="legal">Hüquqi</option>
            <option value="none">Təyin olunmayıb (köhnə)</option>
          </select>
        </div>
        <CardContent className="px-0 pb-0">
          {isLoading ? <TableSkeleton /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : !data || data.data.length === 0 ? <EmptyState title="İstifadəçi tapılmadı" /> : (
            <>
              <Table>
                <TableHeader><TableRow><TableHead>Ad</TableHead><TableHead>E-poçt</TableHead><TableHead>Telefon</TableHead><TableHead>Növ</TableHead><TableHead>Status</TableHead><TableHead>Kompleks</TableHead><TableHead>Qeydiyyat</TableHead></TableRow></TableHeader>
                <TableBody>
                  {data.data.map((u) => (
                    <TableRow key={u.id}>
                      <TableCell className="font-medium">
                        {u.full_name ?? '—'}
                        {u.is_komendant_linked && <Badge variant="secondary" className="ml-2">Komendant</Badge>}
                      </TableCell>
                      <TableCell>{u.email ?? '—'} {u.email && !u.email_verified && <Badge variant="warning" className="ml-1">təsdiqlənməyib</Badge>}</TableCell>
                      <TableCell className="text-muted-foreground">{u.phone ?? '—'}</TableCell>
                      <TableCell>{u.account_type ? <Badge variant="default">{typeLabel[u.account_type]}</Badge> : <Badge variant="muted">—</Badge>}</TableCell>
                      <TableCell><Badge variant={u.status === 'active' ? 'success' : 'muted'}>{u.status}</Badge></TableCell>
                      <TableCell className="space-x-1">
                        {u.complex_ids.length === 0 ? '—' : u.complex_ids.map((c) => <Link key={c} to={`/complexes/${c}`} className="text-primary hover:underline">#{c}</Link>)}
                      </TableCell>
                      <TableCell className="text-muted-foreground">{formatDate(u.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
              <div className="flex items-center justify-end gap-2 p-3 text-sm text-muted-foreground">
                <span>{data.meta.total} nəticə · səhifə {data.meta.page}/{pages}</span>
                <Button size="sm" variant="outline" disabled={page <= 1} onClick={() => setPage(page - 1)}>Əvvəlki</Button>
                <Button size="sm" variant="outline" disabled={page >= pages} onClick={() => setPage(page + 1)}>Növbəti</Button>
              </div>
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
