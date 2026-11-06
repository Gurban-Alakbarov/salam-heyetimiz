import { createContext, useContext, useState } from 'react'
import { ArrowDown, ArrowUp, Check, ChevronRight, Plus, Trash2 } from 'lucide-react'
import type { SiteCatalog, SiteField, SiteLang } from '@/api/siteContent'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { cn } from '@/lib/utils'
import { ImagePicker } from './ImagePicker'
import { blankFields, hasErrorUnder, isObject, isTranslated, type Item, LANGS, move, preview, type Translated } from './model'
import { RichText } from './RichText'

export const EditorContext = createContext<{ catalog: SiteCatalog; errors: Record<string, string[]> } | null>(null)

function useEditor() {
  const context = useContext(EditorContext)
  if (!context) throw new Error('Field outside the site editor')
  return context
}

function FieldError({ path }: { path: string }) {
  const message = useEditor().errors[path]?.[0]
  return message ? <p className="mt-1 text-xs text-destructive">{message}</p> : null
}

/** Grows with the text, so short labels stay one line and long paragraphs stay readable. */
function PlainText({ value, onChange, label }: { value: string; onChange: (text: string) => void; label: string }) {
  const rows = Math.min(8, Math.max(1, Math.ceil(value.length / 42)))
  return <Textarea aria-label={label} rows={rows} className="min-h-9 py-1.5" value={value} onChange={(e) => onChange(e.target.value)} />
}

function TranslatedField({ field, value, path, onChange }: { field: SiteField; value: Translated; path: string; onChange: (value: Translated) => void }) {
  const set = (lang: SiteLang, text: string) => {
    const issues = { ...value._issues }
    if (text) delete issues[lang]
    onChange({ ...value, [lang]: text, _issues: issues })
  }
  const confirm = (lang: SiteLang) => {
    const issues = { ...value._issues }
    delete issues[lang]
    onChange({ ...value, _issues: issues, _confirmed: [...(value._confirmed ?? []), lang] })
  }

  return (
    <div>
      <Label className="text-xs text-muted-foreground">{field.label}</Label>
      <div className="mt-1 grid gap-2 lg:grid-cols-3">
        {LANGS.map((lang) => {
          const issue = value._issues?.[lang]
          return (
            <div key={lang} className="min-w-0">
              <div className="mb-1 flex min-h-5 items-center gap-2 text-[11px] font-medium uppercase text-muted-foreground">
                {lang}
                {issue === 'missing' && <Badge variant="destructive">Tərcümə yoxdur</Badge>}
                {issue === 'stale' && <Badge variant="warning">Yenilənməlidir</Badge>}
                {issue === 'stale' && (
                  <button type="button" onClick={() => confirm(lang)} className="inline-flex items-center gap-0.5 normal-case text-primary hover:underline">
                    <Check className="h-3 w-3" /> Düzgündür
                  </button>
                )}
              </div>
              {field.kind === 'text'
                ? <PlainText label={`${field.label} ${lang}`} value={value[lang]} onChange={(text) => set(lang, text)} />
                : <RichText label={`${field.label} ${lang}`} rich={field.kind === 'rich'} value={value[lang]} onChange={(html) => set(lang, html)} />}
              <FieldError path={`${path}.${lang}`} />
            </div>
          )
        })}
      </div>
    </div>
  )
}

function AddressField({ label, value, path, onChange }: { label: string; value: string; path: string; onChange: (value: string) => void }) {
  return (
    <div>
      <Label className="text-xs text-muted-foreground">{label}</Label>
      <Input className="mt-1" placeholder="/contact" value={value} onChange={(e) => onChange(e.target.value)} />
      <FieldError path={path} />
    </div>
  )
}

function IconField({ label, value, path, onChange }: { label: string; value: string; path: string; onChange: (value: string) => void }) {
  const { catalog } = useEditor()
  return (
    <div>
      <Label className="text-xs text-muted-foreground">{label}</Label>
      <div className="mt-1 flex flex-wrap gap-1">
        {Object.entries(catalog.icons).map(([name, drawing]) => (
          <button
            key={name}
            type="button"
            title={name}
            aria-label={name}
            aria-pressed={name === value}
            onClick={() => onChange(name)}
            className={cn('rounded-md border p-1.5 hover:bg-accent', name === value && 'border-primary bg-primary/10 text-primary')}
          >
            {/* The drawings come from the repository, not from anything an admin typed. */}
            <svg aria-hidden="true" fill="none" width="18" height="18" stroke="currentColor" strokeLinecap="round" strokeLinejoin="round" strokeWidth="1.8" viewBox="0 0 24 24" dangerouslySetInnerHTML={{ __html: drawing }} />
          </button>
        ))}
      </div>
      <FieldError path={path} />
    </div>
  )
}

function ListField({ field, value, path, onChange }: { field: SiteField; value: Item[]; path: string; onChange: (value: Item[]) => void }) {
  const { catalog, errors } = useEditor()
  const [open, setOpen] = useState<Record<string, boolean>>({})
  const fields = field.fields ?? {}

  const add = () => {
    const item = blankFields(fields, catalog)
    onChange([...value, item])
    setOpen({ ...open, [String(item._k)]: true })
  }

  return (
    <div>
      <Label className="text-xs text-muted-foreground">{field.label} ({value.length})</Label>
      <div className="mt-1 space-y-1.5">
        {value.map((item, i) => {
          const key = String(item._k)
          const itemPath = `${path}.${i}`
          const shown = open[key] || hasErrorUnder(errors, itemPath)
          return (
            <div key={key} className="rounded-md border">
              <div className="flex items-center gap-1 px-2 py-1">
                <button type="button" className="flex min-w-0 flex-1 items-center gap-1.5 text-left text-sm" aria-expanded={shown} onClick={() => setOpen({ ...open, [key]: !open[key] })}>
                  <ChevronRight className={cn('h-4 w-4 shrink-0 transition-transform', shown && 'rotate-90')} />
                  <span className="truncate">{preview(item) || `Sətir ${i + 1}`}</span>
                </button>
                <Button type="button" variant="ghost" size="icon" className="h-7 w-7" aria-label="Yuxarı" disabled={i === 0} onClick={() => onChange(move(value, i, i - 1))}><ArrowUp className="h-3.5 w-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" className="h-7 w-7" aria-label="Aşağı" disabled={i === value.length - 1} onClick={() => onChange(move(value, i, i + 1))}><ArrowDown className="h-3.5 w-3.5" /></Button>
                <Button type="button" variant="ghost" size="icon" className="h-7 w-7" aria-label="Sil" onClick={() => onChange(value.filter((_, x) => x !== i))}><Trash2 className="h-3.5 w-3.5" /></Button>
              </div>
              {shown && (
                <div className="border-t p-3">
                  <Fields fields={fields} value={item} path={itemPath} onChange={(next) => onChange(value.map((row, x) => (x === i ? { ...next, _k: item._k } : row)))} />
                </div>
              )}
            </div>
          )
        })}
      </div>
      <Button type="button" variant="outline" size="sm" className="mt-2" onClick={add}><Plus className="h-4 w-4" /> Əlavə et</Button>
      <FieldError path={path} />
    </div>
  )
}

function FieldView({ field, value, path, onChange }: { field: SiteField; value: unknown; path: string; onChange: (value: unknown) => void }) {
  const { catalog } = useEditor()
  const text = typeof value === 'string' ? value : ''

  switch (field.kind) {
    case 'text':
    case 'inline':
    case 'rich':
      return <TranslatedField field={field} value={isTranslated(value) ? value : { az: '', en: '', ru: '' }} path={path} onChange={onChange} />
    case 'link': {
      const link = isObject(value) ? value : {}
      return (
        <div className="space-y-2 rounded-md border border-dashed p-3">
          <TranslatedField field={{ kind: 'text', label: `${field.label}: yazı` }} value={isTranslated(link.label) ? link.label : { az: '', en: '', ru: '' }} path={`${path}.label`} onChange={(label) => onChange({ ...link, label })} />
          <AddressField label={`${field.label}: ünvan`} value={typeof link.href === 'string' ? link.href : ''} path={`${path}.href`} onChange={(href) => onChange({ ...link, href })} />
        </div>
      )
    }
    case 'address':
      return <AddressField label={field.label} value={text} path={path} onChange={onChange} />
    case 'image':
      return (
        <div>
          <Label className="text-xs text-muted-foreground">{field.label}</Label>
          <div className="mt-1"><ImagePicker value={text} onChange={onChange} siteUrl={catalog.site_url} clearable /></div>
          <FieldError path={path} />
        </div>
      )
    case 'icon':
      return <IconField label={field.label} value={text} path={path} onChange={onChange} />
    case 'date':
      return (
        <div>
          <Label className="text-xs text-muted-foreground">{field.label}</Label>
          <Input className="mt-1 max-w-48" type="date" value={text} onChange={(e) => onChange(e.target.value)} />
          <FieldError path={path} />
        </div>
      )
    case 'anchor':
      return (
        <div>
          <Label className="text-xs text-muted-foreground">{field.label}</Label>
          <Input className="mt-1 max-w-64" value={text} onChange={(e) => onChange(e.target.value)} />
          <FieldError path={path} />
        </div>
      )
    case 'group':
      return (
        <div className="rounded-md border p-3">
          <p className="mb-2 text-sm font-medium">{field.label}</p>
          <Fields fields={field.fields ?? {}} value={isObject(value) ? value : {}} path={path} onChange={onChange} />
        </div>
      )
    case 'list':
      return <ListField field={field} value={Array.isArray(value) ? (value as Item[]) : []} path={path} onChange={onChange} />
  }
}

/** Draws every field of a section, a list item or a group from its description in the catalog. */
export function Fields({ fields, value, path, onChange }: { fields: Record<string, SiteField>; value: Record<string, unknown>; path: string; onChange: (value: Record<string, unknown>) => void }) {
  return (
    <div className="space-y-4">
      {Object.entries(fields).map(([name, field]) => (
        <FieldView key={name} field={field} value={value[name]} path={path ? `${path}.${name}` : name} onChange={(next) => onChange({ ...value, [name]: next })} />
      ))}
    </div>
  )
}
