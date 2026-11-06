import { Link } from 'react-router-dom'
import { type SiteDocumentSummary, useSiteDocuments } from '@/api/siteContent'
import { PageHeader } from '@/components/PageHeader'
import { ErrorState, TableSkeleton } from '@/components/states'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Card } from '@/components/ui/card'
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table'
import { formatDateTime } from '@/lib/format'
import { PublishButton, ReleaseList } from './SitePublish'

/** In the order of the website's menu, shared texts last. */
const ORDER = ['index', 'services', 'how-it-works', 'solutions', 'campaigns', 'faq', 'contact', 'privacy', 'terms', 'refund', 'account', 'site']

const sorted = (rows: SiteDocumentSummary[]) => [...rows].sort((a, b) => ORDER.indexOf(a.key) - ORDER.indexOf(b.key))

function TranslationState({ row }: { row: SiteDocumentSummary }) {
  if (row.translations_missing === 0 && row.translations_stale === 0) return <Badge variant="success">Hazırdır</Badge>
  return (
    <div className="flex flex-wrap gap-1">
      {row.translations_missing > 0 && <Badge variant="destructive">{row.translations_missing} çatışmır</Badge>}
      {row.translations_stale > 0 && <Badge variant="warning">{row.translations_stale} yenilənməlidir</Badge>}
    </div>
  )
}

export function SitePagesPage() {
  const query = useSiteDocuments()

  return (
    <div className="space-y-5">
      <PageHeader title="Sayt məzmunu" description="Saytın səhifələri, mətnləri və şəkilləri" actions={<PublishButton />} />

      <Alert>
        <AlertDescription>Saxlanan dəyişikliklər "Yayımla" basılana qədər saytda görünmür.</AlertDescription>
      </Alert>

      <Card className="overflow-hidden">
        {query.isLoading ? (
          <TableSkeleton cols={4} />
        ) : query.isError ? (
          <ErrorState error={query.error} onRetry={() => query.refetch()} />
        ) : (
          <Table>
            <TableHeader>
              <TableRow>
                <TableHead>Səhifə</TableHead>
                <TableHead>Vəziyyət</TableHead>
                <TableHead>Tərcümə</TableHead>
                <TableHead>Son dəyişiklik</TableHead>
              </TableRow>
            </TableHeader>
            <TableBody>
              {sorted(query.data ?? []).map((row) => (
                <TableRow key={row.key}>
                  <TableCell className="font-medium">
                    <Link to={`/site/${row.key}`} className="text-primary hover:underline">{row.title}</Link>
                  </TableCell>
                  <TableCell>
                    {row.has_unpublished_changes ? <Badge variant="warning">Yayımlanmayıb</Badge> : <Badge variant="muted">Saytdakı kimi</Badge>}
                  </TableCell>
                  <TableCell><TranslationState row={row} /></TableCell>
                  <TableCell className="text-muted-foreground">
                    {row.updated_by ? `${formatDateTime(row.updated_at)}, ${row.updated_by}` : 'Dəyişilməyib'}
                  </TableCell>
                </TableRow>
              ))}
            </TableBody>
          </Table>
        )}
      </Card>

      <ReleaseList />
    </div>
  )
}
