import type { ReactNode } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ChevronLeft } from 'lucide-react'
import { useCampaign } from '@/api/campaigns'
import { StatusBadge } from '@/components/StatusBadge'
import { ErrorState, LoadingState } from '@/components/states'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { formatDateTime } from '@/lib/format'

const SCOPE_LABEL: Record<string, string> = {
  all_users: 'Bütün istifadəçilər',
  user_ids: 'Seçilmiş ID-lər',
  filter: 'Filtr',
  complex: 'Kompleks',
}
const LANG_LABEL: Record<string, string> = { az: 'Azərbaycan', ru: 'Rus', en: 'İngilis' }

function Cell({ label, value }: { label: string; value: ReactNode }) {
  return (
    <div className="flex flex-col gap-0.5 py-2">
      <span className="text-xs uppercase tracking-wide text-muted-foreground">{label}</span>
      <span className="text-sm">{value ?? '—'}</span>
    </div>
  )
}

export function CampaignDetailPage() {
  const { id } = useParams()
  const campaignId = Number(id)
  const { data: c, isLoading, isError, error, refetch } = useCampaign(campaignId)

  return (
    <div className="space-y-6">
      <Link to="/notifications" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground">
        <ChevronLeft className="h-4 w-4" />
        Bildirişlər
      </Link>

      {isLoading ? (
        <LoadingState />
      ) : isError || !c ? (
        <ErrorState error={error} onRetry={() => refetch()} />
      ) : (
        <>
          <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 className="text-2xl font-semibold tracking-tight">{c.title}</h1>
            <StatusBadge kind="campaign" value={c.status} />
          </div>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Kampaniya & çatdırılma</CardTitle>
            </CardHeader>
            <CardContent className="grid grid-cols-2 gap-x-6 gap-y-1 sm:grid-cols-3">
              <Cell label="ID" value={`#${c.id}`} />
              <Cell label="Status" value={<StatusBadge kind="campaign" value={c.status} />} />
              <Cell label="Dil" value={LANG_LABEL[c.language] ?? c.language} />
              <Cell label="Auditoriya" value={SCOPE_LABEL[c.audience_scope] ?? c.audience_scope} />
              <Cell label="Alıcı sayı" value={c.total_recipients ?? '—'} />
              <Cell label="Göndərilib" value={c.sent_count} />
              <Cell label="Uğursuz" value={c.failed_count} />
              <Cell label="Yaradan admin" value={`#${c.created_by_admin_id}`} />
              <Cell label="Təsdiq vaxtı" value={formatDateTime(c.confirmed_at)} />
              <Cell label="Göndərilmə vaxtı" value={formatDateTime(c.sent_at)} />
              <Cell label="Yaradılıb" value={formatDateTime(c.created_at)} />
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2">
              <CardTitle className="text-base">Məzmun</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              <div>
                <div className="text-xs uppercase tracking-wide text-muted-foreground">Başlıq</div>
                <div className="text-sm font-medium">{c.title}</div>
              </div>
              <div>
                <div className="text-xs uppercase tracking-wide text-muted-foreground">Mətn</div>
                <p className="whitespace-pre-wrap text-sm">{c.body}</p>
              </div>
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
