import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { Lock } from 'lucide-react'
import { categoryLabels, channelsOf, CHANNEL_BITS, useNotificationTemplates } from '@/api/notificationTemplates'
import { PageHeader } from '@/components/PageHeader'
import { EmptyState, ErrorState, TableSkeleton } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { NotificationsTabs } from './NotificationsTabs'

/** Bildirişlər → Şablonlar (B12): the existing templates, filterable by category / channel. */
export function TemplatesPage() {
  const { data, isLoading, isError, error, refetch } = useNotificationTemplates()
  const [category, setCategory] = useState('')
  const [channel, setChannel] = useState('')

  const rows = useMemo(() => (data ?? []).filter((t) =>
    (!category || t.category === category)
    && (!channel || channelsOf(t.channels_mask).some((c) => c.key === channel)),
  ), [data, category, channel])

  return (
    <div className="space-y-6">
      <NotificationsTabs />
      <PageHeader title="Bildiriş şablonları" description="Mövcud şablonların AZ / RU / EN mətnləri (push + tətbiqdaxili)" />

      <Card className="overflow-hidden">
        <div className="flex flex-wrap items-center gap-2 p-3">
          <select aria-label="Kateqoriya filtri" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={category} onChange={(e) => setCategory(e.target.value)}>
            <option value="">Bütün kateqoriyalar</option>
            {Object.entries(categoryLabels).map(([k, l]) => <option key={k} value={k}>{l}</option>)}
          </select>
          <select aria-label="Kanal filtri" className="h-9 rounded-md border border-input bg-card px-2 text-sm" value={channel} onChange={(e) => setChannel(e.target.value)}>
            <option value="">Bütün kanallar</option>
            {CHANNEL_BITS.map((c) => <option key={c.key} value={c.key}>{c.label}</option>)}
          </select>
        </div>
        {isLoading ? <TableSkeleton /> : isError ? <ErrorState error={error} onRetry={() => refetch()} /> : rows.length === 0 ? <EmptyState title="Şablon tapılmadı" /> : (
          <Table>
            <TableHeader><TableRow><TableHead>Açar</TableHead><TableHead>Kateqoriya</TableHead><TableHead>Kanallar</TableHead><TableHead>Dillər</TableHead><TableHead>Dəyişənlər</TableHead><TableHead>Status</TableHead></TableRow></TableHeader>
            <TableBody>
              {rows.map((t) => (
                <TableRow key={t.id}>
                  <TableCell className="font-medium">
                    <Link to={`/notifications/templates/${t.id}`} className="hover:underline"><code>{t.template_key}</code></Link>
                    {t.read_only && <Badge variant="muted" className="ml-2"><Lock className="mr-1 h-3 w-3" />Yalnız oxunur</Badge>}
                  </TableCell>
                  <TableCell>{categoryLabels[t.category] ?? t.category}</TableCell>
                  <TableCell className="space-x-1">{channelsOf(t.channels_mask).map((c) => <Badge key={c.key} variant="outline">{c.label}</Badge>)}</TableCell>
                  <TableCell>
                    {t.read_only ? <span className="text-xs text-muted-foreground">kampaniyada verilir</span> : (
                      <span className="inline-flex items-center gap-1">
                        {t.locales.map((l) => <Badge key={l.locale} variant={l.present ? 'success' : 'destructive'} title={l.present ? 'mövcuddur' : 'çatışmır'}>{l.locale.toUpperCase()}</Badge>)}
                        {!t.locales_complete && <span className="text-xs text-destructive">natamam</span>}
                      </span>
                    )}
                  </TableCell>
                  <TableCell className="text-xs text-muted-foreground">{t.placeholders.length === 0 ? '—' : t.placeholders.map((p) => `{${p}}`).join(' ')}</TableCell>
                  <TableCell>{t.is_active ? <Badge variant="success">Aktiv</Badge> : <Badge variant="muted">Deaktiv</Badge>}</TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>
    </div>
  )
}
