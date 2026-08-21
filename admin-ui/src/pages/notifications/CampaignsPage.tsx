import { useEffect, useState } from 'react'
import { Link } from 'react-router-dom'
import { Plus } from 'lucide-react'
import { useCampaigns } from '@/api/campaigns'
import { PERM } from '@/auth/permissions'
import { CursorPagination } from '@/components/CursorPagination'
import { PageHeader } from '@/components/PageHeader'
import { PermissionGate } from '@/components/PermissionGate'
import { StatusBadge } from '@/components/StatusBadge'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Button } from '@/components/ui/button'
import { Card } from '@/components/ui/card'
import { Select } from '@/components/ui/select'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'
import { useCursor } from '@/lib/useCursor'

const STATUS_OPTIONS = [
  { value: '', label: 'Bütün statuslar' },
  { value: 'queued', label: 'Növbədə' },
  { value: 'sending', label: 'Göndərilir' },
  { value: 'sent', label: 'Göndərilib' },
  { value: 'failed', label: 'Uğursuz' },
  { value: 'draft', label: 'Qaralama' },
]

const SCOPE_LABEL: Record<string, string> = {
  all_users: 'Bütün istifadəçilər',
  user_ids: 'Seçilmiş ID-lər',
  filter: 'Filtr',
  complex: 'Kompleks',
}

export function CampaignsPage() {
  const [status, setStatus] = useState('')
  const { cursor, next, prev, reset, canPrev, pageIndex } = useCursor()

  useEffect(() => {
    reset()
  }, [status, reset])

  const query = useCampaigns({ status: status || undefined, cursor, limit: 25 })
  const rows = query.data?.data ?? []

  return (
    <div className="space-y-5">
      <PageHeader
        title="Bildirişlər"
        description="Sistem bildiriş kampaniyaları (push + tətbiqdaxili)"
        actions={
          <PermissionGate anyOf={[PERM.notificationsSend]}>
            <Button asChild>
              <Link to="/notifications/new">
                <Plus className="h-4 w-4" />
                Yeni bildiriş
              </Link>
            </Button>
          </PermissionGate>
        }
      />

      <div className="flex flex-col gap-3 sm:flex-row">
        <Select className="sm:w-56" value={status} onChange={(e) => setStatus(e.target.value)}>
          {STATUS_OPTIONS.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </Select>
      </div>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={6} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : rows.length === 0 ? (
          <EmptyState title="Kampaniya tapılmadı" description="Hələ heç bir bildiriş göndərilməyib." />
        ) : (
          <>
            <div className="overflow-x-auto">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Başlıq</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Auditoriya</TableHead>
                    <TableHead className="text-right">Alıcılar</TableHead>
                    <TableHead className="text-right">Göndərilib / Uğursuz</TableHead>
                    <TableHead>Yaradılıb</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  {rows.map((c) => (
                    <TableRow key={c.id}>
                      <TableCell>
                        <Link to={`/notifications/${c.id}`} className="font-medium text-primary hover:underline">
                          {c.title}
                        </Link>
                        <div className="text-xs text-muted-foreground">#{c.id}</div>
                      </TableCell>
                      <TableCell>
                        <StatusBadge kind="campaign" value={c.status} />
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{SCOPE_LABEL[c.audience_scope] ?? c.audience_scope}</TableCell>
                      <TableCell className="whitespace-nowrap text-right">{c.total_recipients ?? '—'}</TableCell>
                      <TableCell className="whitespace-nowrap text-right text-muted-foreground">
                        {c.sent_count} / {c.failed_count}
                      </TableCell>
                      <TableCell className="whitespace-nowrap text-muted-foreground">{formatDateTime(c.created_at)}</TableCell>
                    </TableRow>
                  ))}
                </TableBody>
              </Table>
            </div>
            <CursorPagination
              pageIndex={pageIndex}
              canPrev={canPrev}
              hasMore={query.data?.page.has_more ?? false}
              onPrev={prev}
              onNext={() => next(query.data?.page.next_cursor ?? null)}
              disabled={query.isFetching}
            />
          </>
        )}
      </Card>
    </div>
  )
}
