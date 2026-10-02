import { useEffect, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ChevronLeft, Eye, Loader2, Lock, Save } from 'lucide-react'
import {
  categoryLabels, channelsOf, TEMPLATE_LOCALES, type TemplateLocale, type TemplatePreview,
  useNotificationTemplate, usePreviewTemplate, useUpdateTemplateLocale,
} from '@/api/notificationTemplates'
import { useAuth } from '@/auth/useAuth'
import { PERM } from '@/auth/permissions'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { ErrorState, LoadingState } from '@/components/states'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { useToast } from '@/components/ui/toast'
import { NotificationsTabs } from './NotificationsTabs'

const SUBJECT_MAX = 120
const BODY_MAX = 1000
const localeLabels: Record<TemplateLocale, string> = { az: 'AZ', ru: 'RU', en: 'EN' }
type Draft = { subject: string; body: string }

/**
 * Template editor (B12). AZ / RU / EN tabs keep their own draft, so switching locale never loses edits.
 * notifications.templates.manage → edit + save; otherwise read-only (preview still allowed with .view).
 * Placeholder whitelist and limits are enforced by the server (422 shown inline); system.admin_campaign
 * is read-only (copy is supplied per campaign).
 */
export function TemplateDetailPage() {
  const { id } = useParams()
  const templateId = Number(id)
  const { data: t, isLoading, isError, error, refetch } = useNotificationTemplate(templateId)
  const { hasPermission } = useAuth()
  const { toast } = useToast()
  const save = useUpdateTemplateLocale(templateId)
  const preview = usePreviewTemplate(templateId)

  const [locale, setLocale] = useState<TemplateLocale>('az')
  const [drafts, setDrafts] = useState<Record<TemplateLocale, Draft> | null>(null)
  const [shown, setShown] = useState<TemplatePreview | null>(null)

  // (Re)load drafts from the stored copy whenever the template data changes (initial load / after save).
  useEffect(() => {
    if (!t) return
    const next = {} as Record<TemplateLocale, Draft>
    for (const l of TEMPLATE_LOCALES) {
      const row = t.locales.find((r) => r.locale === l)
      next[l] = { subject: row?.subject ?? '', body: row?.body ?? '' }
    }
    setDrafts(next)
  }, [t])

  useEffect(() => { setShown(null); preview.reset(); save.reset() }, [locale]) // eslint-disable-line react-hooks/exhaustive-deps

  if (isLoading || (t && !drafts)) return <LoadingState />
  if (isError || !t || !drafts) return <ErrorState error={error} onRetry={() => refetch()} />

  const canEdit = hasPermission(PERM.notificationTemplatesManage) && !t.read_only
  const stored = t.locales.find((r) => r.locale === locale)
  const draft = drafts[locale]
  const isDirty = (l: TemplateLocale) => {
    const row = t.locales.find((r) => r.locale === l)
    return drafts[l].subject !== (row?.subject ?? '') || drafts[l].body !== (row?.body ?? '')
  }
  const setDraft = (patch: Partial<Draft>) => {
    // an edited draft invalidates the previous server verdict (stale 422 / result)
    if (save.error) save.reset()
    if (preview.error) preview.reset()
    setDrafts({ ...drafts, [locale]: { ...draft, ...patch } })
  }
  const fieldError = (f: 'subject' | 'body') => {
    const e = save.error ?? preview.error
    return e instanceof ApiError ? e.fieldError(f) : undefined
  }
  const generalError = [save.error, preview.error].find((e) => e instanceof ApiError && e.status !== 422) as ApiError | undefined

  const onSave = () => save.mutate(
    { locale, subject: draft.subject, body: draft.body },
    { onSuccess: () => toast({ variant: 'success', title: `${localeLabels[locale]} mətni yadda saxlanıldı` }) },
  )
  const onPreview = () => {
    if (save.error) save.reset()
    preview.mutate({ locale, subject: draft.subject, body: draft.body }, { onSuccess: (p) => setShown(p) })
  }
  const onSaveClick = () => {
    if (preview.error) preview.reset()
    onSave()
  }
  const insert = (p: string) => setDraft({ body: `${draft.body}${draft.body && !draft.body.endsWith(' ') ? ' ' : ''}{${p}}` })

  return (
    <div className="space-y-6">
      <NotificationsTabs />
      <Link to="/notifications/templates" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ChevronLeft className="h-4 w-4" /> Şablonlar</Link>

      <div className="flex flex-wrap items-center gap-2">
        <h1 className="text-2xl font-semibold tracking-tight"><code>{t.template_key}</code></h1>
        <Badge variant="outline">{categoryLabels[t.category] ?? t.category}</Badge>
        {channelsOf(t.channels_mask).map((c) => <Badge key={c.key} variant="secondary">{c.label}</Badge>)}
        {!t.is_active && <Badge variant="muted">Deaktiv</Badge>}
        {!canEdit && !t.read_only && <Badge variant="muted"><Lock className="mr-1 h-3 w-3" />Yalnız baxış</Badge>}
      </div>

      {t.read_only ? (
        <Alert>
          <Lock className="h-4 w-4" />
          <AlertDescription>
            Bu şablon (<code>system.admin_campaign</code>) yalnız oxunur: bildiriş mətni hər kampaniyada ayrıca daxil edilir və burada redaktə və ya önizləmə olunmur.
            {' '}<Link to="/notifications" className="font-medium text-primary hover:underline">Kampaniyalara keç →</Link>
          </AlertDescription>
        </Alert>
      ) : (
        <div className="grid gap-6 lg:grid-cols-[1fr_340px]">
          <Card>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
              <div className="inline-flex rounded-md bg-muted p-1 text-sm" role="tablist" aria-label="Dil">
                {TEMPLATE_LOCALES.map((l) => (
                  <button key={l} type="button" role="tab" aria-selected={locale === l} onClick={() => setLocale(l)}
                    className={`rounded px-3 py-1.5 font-medium ${locale === l ? 'bg-card shadow-sm' : 'text-muted-foreground hover:text-foreground'}`}>
                    {localeLabels[l]}{isDirty(l) && <span className="ml-1 text-warning" title="Saxlanmamış dəyişiklik">●</span>}
                    {!t.locales.some((r) => r.locale === l) && <span className="ml-1 text-destructive" title="Bu dil üçün mətn yoxdur">!</span>}
                  </button>
                ))}
              </div>
              <span className="text-xs text-muted-foreground">
                {stored?.updated_at ? <>Son dəyişiklik: {formatDateTime(stored.updated_at)}{stored.updated_by_admin_id ? ` · admin #${stored.updated_by_admin_id}` : ' · seed'}</> : 'Hələ redaktə olunmayıb'}
              </span>
            </CardHeader>
            <CardContent className="space-y-4">
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <Label htmlFor="tpl-subject">Başlıq (push title)</Label>
                  <span className={`text-xs ${draft.subject.length > SUBJECT_MAX ? 'text-destructive' : 'text-muted-foreground'}`}>{draft.subject.length}/{SUBJECT_MAX}</span>
                </div>
                <Input id="tpl-subject" value={draft.subject} readOnly={!canEdit} onChange={(e) => setDraft({ subject: e.target.value })} />
                {fieldError('subject') && <p className="text-xs text-destructive">{fieldError('subject')}</p>}
              </div>
              <div className="space-y-1">
                <div className="flex items-center justify-between">
                  <Label htmlFor="tpl-body">Mətn</Label>
                  <span className={`text-xs ${draft.body.length > BODY_MAX ? 'text-destructive' : 'text-muted-foreground'}`}>{draft.body.length}/{BODY_MAX}</span>
                </div>
                <Textarea id="tpl-body" rows={5} value={draft.body} readOnly={!canEdit} onChange={(e) => setDraft({ body: e.target.value })} />
                {fieldError('body') && <p className="text-xs text-destructive">{fieldError('body')}</p>}
              </div>

              <div className="space-y-1">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">İcazəli dəyişənlər</p>
                {t.placeholders.length === 0 ? (
                  <p className="text-xs text-muted-foreground">Bu şablonda dəyişən yoxdur — mətn statikdir. Hər hansı <code>{'{…}'}</code> rədd ediləcək.</p>
                ) : (
                  <div className="flex flex-wrap gap-1">
                    {t.placeholders.map((p) => (
                      <button key={p} type="button" disabled={!canEdit} onClick={() => insert(p)} title={canEdit ? 'Mətnə əlavə et' : undefined}
                        className="rounded-full border bg-muted px-2 py-0.5 font-mono text-xs disabled:cursor-default">{`{${p}}`}</button>
                    ))}
                  </div>
                )}
              </div>

              {generalError && <p className="text-sm text-destructive">{generalError.message}</p>}

              <div className="flex flex-wrap gap-2">
                <Button variant="outline" onClick={onPreview} disabled={preview.isPending}>
                  {preview.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Eye className="h-4 w-4" />} Önizləmə
                </Button>
                {canEdit && (
                  <Button onClick={onSaveClick} disabled={save.isPending || !isDirty(locale)}>
                    {save.isPending ? <Loader2 className="h-4 w-4 animate-spin" /> : <Save className="h-4 w-4" />} Yadda saxla ({localeLabels[locale]})
                  </Button>
                )}
              </div>
            </CardContent>
          </Card>

          <Card>
            <CardHeader className="pb-2"><CardTitle className="text-base">Önizləmə</CardTitle></CardHeader>
            <CardContent className="space-y-3">
              {shown ? (
                <>
                  <div className="rounded-xl border bg-muted/40 p-3 shadow-sm">
                    <p className="text-xs text-muted-foreground">Salam Həyətimiz · indi</p>
                    <p className="text-sm font-semibold">{shown.title || '—'}</p>
                    <p className="text-sm">{shown.body || '—'}</p>
                  </div>
                  {Object.keys(shown.sample_variables).length > 0 && (
                    <p className="text-xs text-muted-foreground">Nümunə: {Object.entries(shown.sample_variables).map(([k, v]) => `{${k}} = ${v}`).join(', ')}</p>
                  )}
                </>
              ) : <p className="text-sm text-muted-foreground">Cari mətni nümunə dəyərlərlə görmək üçün "Önizləmə" düyməsini basın.</p>}
              <p className="text-xs text-muted-foreground">Önizləmə heç nə saxlamır və heç kimə bildiriş göndərmir.</p>
            </CardContent>
          </Card>
        </div>
      )}
    </div>
  )
}
