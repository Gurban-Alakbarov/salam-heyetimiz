import { Fragment, useEffect, useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { type AuditFilters, useAudit } from '@/api/admins'
import { CursorPagination } from '@/components/CursorPagination'
import { EmptyState, ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'
import { useDebouncedValue } from '@/lib/useDebouncedValue'

/** Areas = action prefixes the backend writes. */
const AREAS: { v: string; l: string }[] = [
  { v: '', l: 'Bütün sahələr' },
  { v: 'device.', l: 'Cihazlar' },
  { v: 'roster.', l: 'Cihaz istifadəçiləri' },
  { v: 'resident.', l: 'Sakinlər' },
  { v: 'subscription.', l: 'Abunəliklər' },
  { v: 'complex.', l: 'Komplekslər' },
  { v: 'invitation.', l: 'Dəvətlər' },
  { v: 'family', l: 'Ailə' },
  { v: 'visitor', l: 'Qonaq linkləri' },
  { v: 'order.', l: 'Sifarişlər' },
  { v: 'refund', l: 'Geri qaytarmalar' },
  { v: 'payment', l: 'Ödənişlər' },
  { v: 'notification', l: 'Bildirişlər' },
  { v: 'application', l: 'Müraciətlər' },
  { v: 'admin.', l: 'Adminlər' },
  { v: 'access.', l: 'İcazələr' },
  { v: 'settings', l: 'Parametrlər' },
  { v: 'command', l: 'Qapı açılışları' },
]

const TYPES: { v: string; l: string; to?: (id: number) => string }[] = [
  { v: '', l: 'Hər obyekt' },
  { v: 'Device', l: 'Cihaz', to: (id) => `/devices/${id}` },
  { v: 'User', l: 'Sakin / istifadəçi' },
  { v: 'Complex', l: 'Kompleks', to: (id) => `/complexes/${id}` },
  { v: 'Order', l: 'Sifariş', to: (id) => `/orders/${id}` },
  { v: 'Subscription', l: 'Abunəlik', to: (id) => `/subscriptions/${id}` },
  { v: 'AdminUser', l: 'Admin' },
]
const typeLabel = (cls: string) => TYPES.find((t) => t.v && cls.endsWith(t.v))
const shortType = (cls: string | null) => (cls ? cls.split('\\').pop() ?? cls : null)

/** Audit log with filters: area, object, admin (free text), dates — and real back/forward paging. */
export function AuditPage() {
  const [params, setParams] = useSearchParams()
  const [area, setArea] = useState(params.get('action_prefix') ?? '')
  const [type, setType] = useState(params.get('auditable_type') ?? '')
  const [objectId, setObjectId] = useState(params.get('auditable_id') ?? '')
  const [text, setText] = useState(params.get('q') ?? '')
  const [since, setSince] = useState('')
  const [until, setUntil] = useState('')
  const q = useDebouncedValue(text.trim(), 400)
  const cursor = useCursor()
  const [open, setOpen] = useState<number | null>(null)

  const filters: AuditFilters = {
    action_prefix: area || undefined,
    auditable_type: type || undefined,
    auditable_id: objectId && /^\d+$/.test(objectId) ? Number(objectId) : undefined,
    q: q || undefined,
    since: since ? `${since} 00:00:00` : undefined,
    until: until ? `${until} 23:59:59` : undefined,
  }
  const key = JSON.stringify(filters)
  // a new filter starts from the first page; keep the URL shareable
  useEffect(() => {
    cursor.reset()
    const next: Record<string, string> = {}
    if (area) next.action_prefix = area
    if (type) next.auditable_type = type
    if (objectId) next.auditable_id = objectId
    if (q) next.q = q
    setParams(next, { replace: true })
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [key])

  const { data, isLoading, isError, error, refetch, isFetching } = useAudit(cursor.cursor, filters)
  const rows = data?.data ?? []
  const changesOf = (p: Record<string, unknown> | null): Array<{ key: string; old?: string; new?: string }> =>
    (p && Array.isArray((p as { changes?: unknown }).changes) ? (p as { changes: Array<{ key: string; old?: string; new?: string }> }).changes : [])
  const clear = () => { setArea(''); setType(''); setObjectId(''); setText(''); setSince(''); setUntil('') }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Audit jurnalı</h1>
        <p className="text-sm text-muted-foreground">Kim, nə vaxt, nəyi etdi. Sahə, obyekt və ya mətnlə süzün (məs. telefon, IMEI, admin adı).</p>
      </div>

      <Card>
        <CardContent className="flex flex-wrap items-end gap-3 pt-6">
          <div className="space-y-1"><span className="text-xs text-muted-foreground">Sahə</span>
            <Select aria-label="Sahə" value={area} onChange={(e) => setArea(e.target.value)}>{AREAS.map((a) => <option key={a.v} value={a.v}>{a.l}</option>)}</Select>
          </div>
          <div className="space-y-1"><span className="text-xs text-muted-foreground">Obyekt</span>
            <div className="flex gap-1">
              <Select aria-label="Obyekt növü" value={type} onChange={(e) => setType(e.target.value)}>{TYPES.map((t) => <option key={t.v} value={t.v}>{t.l}</option>)}</Select>
              <Input aria-label="Obyekt nömrəsi" placeholder="№" className="w-20" value={objectId} onChange={(e) => setObjectId(e.target.value.replace(/\D/g, ''))} />
            </div>
          </div>
          <div className="space-y-1"><span className="text-xs text-muted-foreground">Mətn</span>
            <Input placeholder="Admin, telefon, IMEI…" className="w-56" value={text} onChange={(e) => setText(e.target.value)} />
          </div>
          <div className="space-y-1"><span className="text-xs text-muted-foreground">Tarixdən</span>
            <Input type="date" value={since} onChange={(e) => setSince(e.target.value)} />
          </div>
          <div className="space-y-1"><span className="text-xs text-muted-foreground">Tarixədək</span>
            <Input type="date" value={until} onChange={(e) => setUntil(e.target.value)} />
          </div>
          <Button variant="ghost" onClick={clear}>Təmizlə</Button>
        </CardContent>
      </Card>

      <Card className="overflow-hidden">
        <CardContent className="px-0 pb-0">
          {isLoading ? (
            <div className="p-6"><LoadingState /></div>
          ) : isError ? (
            <div className="p-6"><ErrorState error={error} onRetry={() => refetch()} /></div>
          ) : rows.length === 0 ? (
            <EmptyState title="Bu süzgəclə qeyd tapılmadı" />
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Vaxt</TableHead>
                    <TableHead>Kim</TableHead>
                    <TableHead>Əməliyyat</TableHead>
                    <TableHead>Obyekt</TableHead>
                    <TableHead>IP</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((row) => {
                    const changes = changesOf(row.payload)
                    const expandable = changes.length > 0 || !!row.payload || !!row.user_agent
                    const t = row.auditable_type ? typeLabel(row.auditable_type) : undefined
                    const objectLabel = row.auditable_type ? `${t?.l ?? shortType(row.auditable_type)} №${row.auditable_id}` : '—'
                    return (
                      <Fragment key={row.id}>
                        <TableRow className={expandable ? 'cursor-pointer' : ''} onClick={() => expandable && setOpen(open === row.id ? null : row.id)}>
                          <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(row.created_at)}</TableCell>
                          <TableCell>
                            {row.actor_label ?? (row.actor_kind === 'system' ? 'Sistem' : row.actor_kind)}
                            {row.impersonator_admin_id != null && (
                              <Badge variant="muted" className="ml-2" title="Başqa admin bu adminin adından daxil olub">başqasının adından</Badge>
                            )}
                          </TableCell>
                          <TableCell><code className="rounded bg-muted px-1 text-xs">{row.action}</code></TableCell>
                          <TableCell className="text-muted-foreground" onClick={(e) => e.stopPropagation()}>
                            {t?.to && row.auditable_id ? <Link to={t.to(row.auditable_id)} className="text-primary hover:underline">{objectLabel}</Link> : objectLabel}
                          </TableCell>
                          <TableCell className="text-muted-foreground">{row.ip ?? '—'}</TableCell>
                        </TableRow>
                        {open === row.id && expandable && (
                          <TableRow>
                            <TableCell colSpan={5} className="bg-muted/40 text-xs">
                              {changes.length > 0 ? (
                                <div className="space-y-1">
                                  <p className="font-medium">Dəyişikliklər (köhnə → yeni)</p>
                                  {changes.map((c) => (
                                    <div key={c.key} className="font-mono">
                                      <span className="text-muted-foreground">{c.key}:</span>{' '}
                                      <span className="text-destructive">{String(c.old ?? '∅')}</span> →{' '}
                                      <span className="text-emerald-600">{String(c.new ?? '∅')}</span>
                                    </div>
                                  ))}
                                </div>
                              ) : row.payload && (
                                <pre className="whitespace-pre-wrap break-all font-mono">{JSON.stringify(row.payload, null, 2)}</pre>
                              )}
                              {row.user_agent && <p className="mt-2 text-muted-foreground">Brauzer: {row.user_agent}</p>}
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    )
                  })}
                </TableBody>
              </Table>
              <CursorPagination
                pageIndex={cursor.pageIndex}
                canPrev={cursor.canPrev}
                hasMore={!!data?.page.has_more}
                onPrev={cursor.prev}
                onNext={() => cursor.next(data?.page.next_cursor ?? null)}
                disabled={isFetching}
              />
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
