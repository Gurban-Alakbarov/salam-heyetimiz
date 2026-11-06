import { useCallback, useEffect, useMemo, useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { ArrowDown, ArrowLeft, ArrowUp, ChevronRight, History, Loader2, Plus, Trash2 } from 'lucide-react'
import {
  type SiteCatalog,
  type SiteContent,
  type SiteDocument,
  useRestoreSiteRevision,
  useSaveSiteDocument,
  useSiteCatalog,
  useSiteDocument,
  useSiteRevisions,
} from '@/api/siteContent'
import { ConfirmDialog } from '@/components/ConfirmDialog'
import { PageHeader } from '@/components/PageHeader'
import { ErrorState, LoadingState } from '@/components/states'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Select } from '@/components/ui/select'
import { useToast } from '@/components/ui/toast'
import { ApiError } from '@/lib/api'
import { formatDateTime } from '@/lib/format'
import { cn } from '@/lib/utils'
import { EditorContext, Fields } from './FieldEditors'
import { blankFields, hasErrorUnder, type Item, move, prepare, preview } from './model'

/** One of each per page: they carry the page's own layout or the contact form. */
const NOT_ADDABLE = ['home-hero', 'contact-form', 'legal']

function VersionHistory({ documentKey, current, dirty, onRestored }: { documentKey: string; current: number; dirty: boolean; onRestored: (document: SiteDocument) => void }) {
  const [open, setOpen] = useState(false)
  const [target, setTarget] = useState<number | null>(null)
  const revisions = useSiteRevisions(documentKey, open)
  const restore = useRestoreSiteRevision(documentKey)
  const { toast } = useToast()

  const confirm = () => {
    if (target === null) return
    restore.mutate({ revision: target, version: current }, {
      onSuccess: (document) => {
        onRestored(document)
        setTarget(null)
        setOpen(false)
        toast({ variant: 'success', title: `${target}-ci versiya bərpa olundu` })
      },
      onError: (err) => toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined }),
    })
  }

  return (
    <>
      <Button type="button" variant="outline" size="sm" onClick={() => setOpen(true)}><History className="h-4 w-4" /> Tarixçə</Button>
      <Dialog open={open} onOpenChange={setOpen}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>Tarixçə</DialogTitle>
            <DialogDescription>{dirty ? 'Əvvəlcə dəyişiklikləri yadda saxlayın.' : 'Əvvəlki versiyaya qayıdanda o, yeni versiya kimi saxlanılır.'}</DialogDescription>
          </DialogHeader>
          {revisions.isLoading ? <LoadingState /> : revisions.isError ? <ErrorState error={revisions.error} onRetry={() => revisions.refetch()} /> : (
            <ul className="max-h-[60vh] divide-y overflow-y-auto">
              {revisions.data?.map((row) => (
                <li key={row.version} className="flex items-center justify-between gap-3 py-2 text-sm">
                  <div className="min-w-0">
                    <p className="font-medium">
                      Versiya {row.version}
                      {row.published && <Badge variant="success" className="ml-2">Saytdadır</Badge>}
                      {row.version === current && <Badge variant="muted" className="ml-2">İndiki</Badge>}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {formatDateTime(row.created_at)}{row.admin ? `, ${row.admin}` : ''}
                      {row.restored_from ? ` (${row.restored_from}-ci versiyadan)` : ''}
                    </p>
                  </div>
                  <Button type="button" variant="outline" size="sm" disabled={dirty || row.version === current} onClick={() => setTarget(row.version)}>Qayıt</Button>
                </li>
              ))}
            </ul>
          )}
        </DialogContent>
      </Dialog>
      <ConfirmDialog
        open={target !== null}
        onOpenChange={(value) => { if (!value) setTarget(null) }}
        title={`${target}-ci versiyaya qayıdılsın?`}
        description="İndiki mətn itmir, tarixçədə qalır."
        confirmLabel="Qayıt"
        loading={restore.isPending}
        onConfirm={confirm}
      />
    </>
  )
}

function SectionCard({ section, path, catalog, errors, first, last, onChange, onMove, onRemove }: {
  section: Item
  path: string
  catalog: SiteCatalog
  errors: Record<string, string[]>
  first: boolean
  last: boolean
  onChange: (section: Item) => void
  onMove: (step: number) => void
  onRemove: () => void
}) {
  const [open, setOpen] = useState(false)
  const type = catalog.section_types[String(section.type)]
  const shown = open || hasErrorUnder(errors, path)

  return (
    <Card>
      <div className="flex items-center gap-1 p-2">
        <button type="button" className="flex min-w-0 flex-1 items-center gap-2 px-1 text-left" aria-expanded={shown} onClick={() => setOpen(!open)}>
          <ChevronRight className={cn('h-4 w-4 shrink-0 transition-transform', shown && 'rotate-90')} />
          <span className="shrink-0 text-sm font-medium">{type?.label ?? String(section.type)}</span>
          <span className="truncate text-sm text-muted-foreground">{preview(section)}</span>
        </button>
        <Button type="button" variant="ghost" size="icon" className="h-8 w-8" aria-label="Yuxarı" disabled={first} onClick={() => onMove(-1)}><ArrowUp className="h-4 w-4" /></Button>
        <Button type="button" variant="ghost" size="icon" className="h-8 w-8" aria-label="Aşağı" disabled={last} onClick={() => onMove(1)}><ArrowDown className="h-4 w-4" /></Button>
        <Button type="button" variant="ghost" size="icon" className="h-8 w-8" aria-label="Bölməni sil" onClick={onRemove}><Trash2 className="h-4 w-4" /></Button>
      </div>
      {shown && type && (
        <CardContent className="border-t pt-4">
          <Fields fields={type.fields} value={section} path={path} onChange={(next) => onChange(next as Item)} />
        </CardContent>
      )}
    </Card>
  )
}

function Editor({ document, catalog }: { document: SiteDocument; catalog: SiteCatalog }) {
  const key = document.key
  const shared = key === 'site'
  const [content, setContent] = useState<SiteContent>(() => prepare(document.content, document.translation_issues))
  const [version, setVersion] = useState(document.version)
  const [dirty, setDirty] = useState(false)
  const [errors, setErrors] = useState<Record<string, string[]>>({})
  const [newType, setNewType] = useState('')
  const [removing, setRemoving] = useState<number | null>(null)
  const save = useSaveSiteDocument(key)
  const { toast } = useToast()

  const change = useCallback((next: SiteContent) => {
    setContent(next)
    setDirty(true)
  }, [])

  const load = useCallback((fresh: SiteDocument) => {
    setContent((current) => prepare(fresh.content, fresh.translation_issues, current))
    setVersion(fresh.version)
    setDirty(false)
    setErrors({})
  }, [])

  // Closing the tab or following a link would lose unsaved text.
  useEffect(() => {
    if (!dirty) return
    const warn = (e: BeforeUnloadEvent) => e.preventDefault()
    const guard = (e: MouseEvent) => {
      const link = (e.target as Element).closest('a[href]')
      if (link && !window.confirm('Yadda saxlanmamış dəyişikliklər itəcək. Davam edilsin?')) {
        e.preventDefault()
        e.stopPropagation()
      }
    }
    window.addEventListener('beforeunload', warn)
    window.document.addEventListener('click', guard, true)
    return () => {
      window.removeEventListener('beforeunload', warn)
      window.document.removeEventListener('click', guard, true)
    }
  }, [dirty])

  const submit = () => {
    save.mutate({ content, version }, {
      onSuccess: (saved) => {
        load(saved)
        toast({ variant: 'success', title: 'Yadda saxlanıldı' })
      },
      onError: (err) => {
        if (err instanceof ApiError && err.status === 422) {
          setErrors(err.fields ?? {})
          toast({ variant: 'destructive', title: 'Səhv olan sahələr var', description: 'Qırmızı yazılan yerlərə baxın.' })
          return
        }
        toast({ variant: 'destructive', title: 'Xəta', description: err instanceof ApiError ? err.message : undefined })
      },
    })
  }

  const sections = (Array.isArray(content.sections) ? content.sections : []) as Item[]
  const setSections = (next: Item[]) => change({ ...content, sections: next })
  const addable = useMemo(() => Object.entries(catalog.section_types).filter(([type]) => !NOT_ADDABLE.includes(type)), [catalog])

  const add = () => {
    const type = catalog.section_types[newType]
    if (!type) return
    setSections([...sections, { ...blankFields(type.fields, catalog), type: newType }])
    setNewType('')
  }

  const context = useMemo(() => ({ catalog, errors }), [catalog, errors])

  return (
    <EditorContext.Provider value={context}>
      <div className="space-y-5 pb-20">
        <Link to="/site" className="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"><ArrowLeft className="h-4 w-4" /> Sayt məzmunu</Link>
        <PageHeader
          title={document.title}
          description={`Versiya ${version}`}
          actions={<VersionHistory documentKey={key} current={version} dirty={dirty} onRestored={load} />}
        />

        {key === 'campaigns' && (
          <p className="text-sm text-muted-foreground">Aktiv kampaniya olmayanda bu səhifə və menyu linki saytda görünmür. Son günü keçən kampaniya siyahıdan çıxır.</p>
        )}

        {shared ? (
          <Card>
            <CardContent className="pt-6">
              <Fields
                fields={catalog.shared_fields}
                value={(content.texts ?? {}) as Record<string, unknown>}
                path="texts"
                onChange={(texts) => change({ ...content, texts })}
              />
            </CardContent>
          </Card>
        ) : (
          <>
            <details className="rounded-lg border bg-card">
              <summary className="cursor-pointer p-3 text-sm font-medium">Axtarış və paylaşım (SEO)</summary>
              <div className="border-t p-4">
                <Fields fields={catalog.page_fields} value={content} path="" onChange={(next) => change(next)} />
              </div>
            </details>

            <div className="space-y-2">
              {sections.map((section, i) => (
                <SectionCard
                  key={String(section._k)}
                  section={section}
                  path={`sections.${i}`}
                  catalog={catalog}
                  errors={errors}
                  first={i === 0}
                  last={i === sections.length - 1}
                  onChange={(next) => setSections(sections.map((row, x) => (x === i ? { ...next, _k: section._k } : row)))}
                  onMove={(step) => setSections(move(sections, i, i + step))}
                  onRemove={() => setRemoving(i)}
                />
              ))}
            </div>

            <div className="flex flex-wrap items-center gap-2">
              <Select className="sm:w-64" aria-label="Yeni bölmə növü" value={newType} onChange={(e) => setNewType(e.target.value)}>
                <option value="">Bölmə seçin</option>
                {addable.map(([type, info]) => <option key={type} value={type}>{info.label}</option>)}
              </Select>
              <Button type="button" variant="outline" disabled={!newType} onClick={add}><Plus className="h-4 w-4" /> Bölmə əlavə et</Button>
            </div>

            <ConfirmDialog
              open={removing !== null}
              onOpenChange={(value) => { if (!value) setRemoving(null) }}
              title="Bölmə silinsin?"
              description="Yadda saxlayandan sonra da tarixçədən qaytarmaq olar."
              confirmLabel="Sil"
              confirmVariant="destructive"
              onConfirm={() => {
                setSections(sections.filter((_, i) => i !== removing))
                setRemoving(null)
              }}
            />
          </>
        )}

        <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-card/95 px-4 py-3 backdrop-blur md:left-64">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">{dirty ? 'Yadda saxlanmamış dəyişikliklər var' : 'Bütün dəyişikliklər yadda saxlanılıb'}</p>
            <Button type="button" disabled={!dirty || save.isPending} onClick={submit}>
              {save.isPending && <Loader2 className="h-4 w-4 animate-spin" />}
              Yadda saxla
            </Button>
          </div>
        </div>
      </div>
    </EditorContext.Provider>
  )
}

export function SiteEditorPage() {
  const { key = '' } = useParams()
  const catalog = useSiteCatalog()
  const document = useSiteDocument(key)

  if (catalog.isLoading || document.isLoading) return <LoadingState />
  if (catalog.isError) return <ErrorState error={catalog.error} onRetry={() => catalog.refetch()} />
  if (document.isError) return <ErrorState error={document.error} onRetry={() => document.refetch()} />
  if (!catalog.data || !document.data) return null

  return <Editor key={key} document={document.data} catalog={catalog.data} />
}
