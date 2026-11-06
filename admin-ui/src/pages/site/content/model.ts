import type { SiteCatalog, SiteContent, SiteField, SiteLang, TranslationIssue } from '@/api/siteContent'

export const LANGS: SiteLang[] = ['az', 'en', 'ru']

/** A value with one text per language, as stored. `_issues` is only for this screen; the server ignores it. */
export interface Translated {
  az: string
  en: string
  ru: string
  _issues?: Partial<Record<SiteLang, TranslationIssue['state']>>
  _confirmed?: SiteLang[]
}

export type Item = Record<string, unknown> & { _k?: string }

export function isObject(value: unknown): value is Record<string, unknown> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

export function isTranslated(value: unknown): value is Translated {
  return isObject(value) && 'az' in value
}

let counter = 0
const nextKey = () => `k${++counter}`

/** An empty value of a field, so a new section or list item has everything its template reads. */
export function blankValue(field: SiteField, catalog: SiteCatalog): unknown {
  switch (field.kind) {
    case 'text':
    case 'inline':
    case 'rich':
      return { az: '', en: '', ru: '' }
    case 'link':
      return { label: { az: '', en: '', ru: '' }, href: '' }
    case 'icon':
      return Object.keys(catalog.icons)[0] ?? ''
    case 'group':
      return blankFields(field.fields ?? {}, catalog)
    case 'list':
      return []
    default:
      return ''
  }
}

export function blankFields(fields: Record<string, SiteField>, catalog: SiteCatalog): Item {
  const out: Item = { _k: nextKey() }
  for (const [name, field] of Object.entries(fields)) out[name] = blankValue(field, catalog)
  return out
}

/**
 * Prepares server content for editing. List items get a stable key, so a moved item keeps its open state and its text
 * cursor. Translation issues move onto the value itself, because their paths stop matching as soon as sections move.
 * `previous` keeps the keys of what is on screen after a save.
 */
export function prepare(content: SiteContent, issues: TranslationIssue[], previous?: SiteContent): SiteContent {
  const byPath = new Map<string, Translated['_issues']>()
  for (const issue of issues) byPath.set(issue.path, { ...byPath.get(issue.path), [issue.lang]: issue.state })

  const walk = (value: unknown, path: string, earlier: unknown): unknown => {
    if (Array.isArray(value)) {
      const before = Array.isArray(earlier) ? earlier : []
      return value.map((item, i) => {
        const prior = before[i]
        const next = walk(item, `${path}.${i}`, prior)
        if (!isObject(next)) return next
        return { ...next, _k: isObject(prior) && typeof prior._k === 'string' ? prior._k : nextKey() }
      })
    }
    if (isTranslated(value)) return { ...value, _issues: byPath.get(path) }
    if (isObject(value)) {
      const out: Record<string, unknown> = {}
      for (const [name, child] of Object.entries(value)) {
        out[name] = walk(child, path ? `${path}.${name}` : name, isObject(earlier) ? earlier[name] : undefined)
      }
      return out
    }
    return value
  }

  return walk(content, '', previous) as SiteContent
}

const plain = (html: string) => html.replace(/<br\s*\/?>/g, ' ').replace(/<[^>]+>/g, '').trim()

/** A short line telling sections and list items apart while they are closed. */
export function preview(item: Record<string, unknown>): string {
  for (const name of ['title', 'q', 'kicker', 'name', 'text', 'label']) {
    const value = item[name]
    if (isTranslated(value) && value.az) return plain(value.az)
  }
  return ''
}

export function move<T>(list: T[], from: number, to: number): T[] {
  if (to < 0 || to >= list.length) return list
  const next = [...list]
  const [item] = next.splice(from, 1)
  next.splice(to, 0, item)
  return next
}

/** Where to show an image. Uploaded ones are on the public disk until they are published. */
export function imageUrl(path: string, siteUrl: string): string {
  const media = '/landing/media/'
  return path.startsWith(media) ? `${siteUrl}/storage/site-media/${path.slice(media.length)}` : `${siteUrl}${path}`
}

/** True when a validation error sits at or under this path, so a closed section can open itself. */
export function hasErrorUnder(errors: Record<string, string[]>, path: string): boolean {
  return Object.keys(errors).some((key) => key === path || key.startsWith(`${path}.`))
}
