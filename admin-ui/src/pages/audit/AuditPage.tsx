import { Fragment, useState } from 'react'
import { useAudit } from '@/api/admins'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'

export function AuditPage() {
  const [cursor, setCursor] = useState<string | null>(null)
  const [open, setOpen] = useState<number | null>(null)
  const { data, isLoading, isError, error, refetch } = useAudit(cursor)
  const changesOf = (p: Record<string, unknown> | null): Array<{ key: string; old?: string; new?: string }> =>
    (p && Array.isArray((p as { changes?: unknown }).changes) ? (p as { changes: Array<{ key: string; old?: string; new?: string }> }).changes : [])

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-semibold tracking-tight">Audit jurnalı</h1>

      <Card className="overflow-hidden">
        <CardHeader className="pb-2">
          <CardTitle className="text-base">Administrativ əməliyyatlar</CardTitle>
        </CardHeader>
        <CardContent className="px-0 pb-0">
          {isLoading ? (
            <div className="p-6"><LoadingState /></div>
          ) : isError ? (
            <div className="p-6"><ErrorState error={error} onRetry={() => refetch()} /></div>
          ) : (
            <>
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Vaxt</TableHead>
                    <TableHead>Aktor</TableHead>
                    <TableHead>Əməliyyat</TableHead>
                    <TableHead>Obyekt</TableHead>
                    <TableHead>IP</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {(data?.data ?? []).map((row) => {
                    const changes = changesOf(row.payload)
                    const expandable = changes.length > 0 || !!row.user_agent
                    return (
                      <Fragment key={row.id}>
                        <TableRow className={expandable ? 'cursor-pointer' : ''} onClick={() => expandable && setOpen(open === row.id ? null : row.id)}>
                          <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(row.created_at)}</TableCell>
                          <TableCell>
                            {row.actor_label ?? row.actor_kind}
                            {row.impersonator_admin_id != null && (
                              <Badge variant="muted" className="ml-2">impersonation</Badge>
                            )}
                          </TableCell>
                          <TableCell><code className="rounded bg-muted px-1 text-xs">{row.action}</code></TableCell>
                          <TableCell className="text-muted-foreground">
                            {row.auditable_type ? `${row.auditable_type.split('\\').pop()}#${row.auditable_id}` : '—'}
                          </TableCell>
                          <TableCell className="text-muted-foreground">{row.ip ?? '—'}</TableCell>
                        </TableRow>
                        {open === row.id && expandable && (
                          <TableRow>
                            <TableCell colSpan={5} className="bg-muted/40 text-xs">
                              {changes.length > 0 && (
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
                              )}
                              {row.user_agent && <p className="mt-2 text-muted-foreground">User-Agent: {row.user_agent}</p>}
                            </TableCell>
                          </TableRow>
                        )}
                      </Fragment>
                    )
                  })}
                </TableBody>
              </Table>
              {data?.page.has_more && (
                <div className="flex justify-center p-3">
                  <Button variant="outline" onClick={() => setCursor(data.page.next_cursor)}>Daha çox</Button>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
