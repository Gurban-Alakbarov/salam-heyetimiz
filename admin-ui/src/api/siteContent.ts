import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

export type SiteLang = 'az' | 'en' | 'ru'

export type FieldKind = 'text' | 'inline' | 'rich' | 'link' | 'address' | 'image' | 'icon' | 'date' | 'anchor' | 'group' | 'list'

export interface SiteField {
  kind: FieldKind
  label: string
  fields?: Record<string, SiteField>
}

export interface SiteSectionType {
  label: string
  fields: Record<string, SiteField>
}

export interface SiteCatalog {
  languages: SiteLang[]
  section_types: Record<string, SiteSectionType>
  page_fields: Record<string, SiteField>
  shared_fields: Record<string, SiteField>
  icons: Record<string, string>
  site_url: string
}

export interface SiteDocumentSummary {
  key: string
  title: string
  version: number
  published_version: number | null
  has_unpublished_changes: boolean
  translations_missing: number
  translations_stale: number
  updated_by: string | null
  updated_at: string | null
}

export interface TranslationIssue {
  path: string
  lang: SiteLang
  state: 'missing' | 'stale'
}

/** Content follows the section types in the catalog, so its shape is known only at run time. */
export type SiteContent = Record<string, unknown>

export interface SiteDocument extends SiteDocumentSummary {
  content: SiteContent
  translation_issues: TranslationIssue[]
}

export interface SiteRevision {
  version: number
  admin: string | null
  restored_from: number | null
  published: boolean
  created_at: string | null
}

export interface SiteMedia {
  path: string
  url: string
}

export function useSiteCatalog() {
  return useQuery({
    queryKey: ['site-catalog'],
    queryFn: async () => (await api.get<{ data: SiteCatalog }>('/admin/v1/site/catalog')).data.data,
    staleTime: Infinity,
  })
}

export function useSiteDocuments() {
  return useQuery({
    queryKey: ['site-documents'],
    queryFn: async () => (await api.get<{ data: SiteDocumentSummary[] }>('/admin/v1/site/documents')).data.data,
  })
}

export function useSiteDocument(key: string) {
  return useQuery({
    queryKey: ['site-document', key],
    queryFn: async () => (await api.get<{ data: SiteDocument }>(`/admin/v1/site/documents/${key}`)).data.data,
    refetchOnWindowFocus: false,
  })
}

function useStoreDocument(key: string) {
  const qc = useQueryClient()
  return (document: SiteDocument) => {
    qc.setQueryData(['site-document', key], document)
    qc.invalidateQueries({ queryKey: ['site-documents'] })
    qc.invalidateQueries({ queryKey: ['site-revisions', key] })
  }
}

export function useSaveSiteDocument(key: string) {
  const store = useStoreDocument(key)
  return useMutation({
    mutationFn: async (body: { content: SiteContent; version: number }) =>
      (await api.put<{ data: SiteDocument }>(`/admin/v1/site/documents/${key}`, body)).data.data,
    onSuccess: store,
  })
}

export function useSiteRevisions(key: string, enabled: boolean) {
  return useQuery({
    queryKey: ['site-revisions', key],
    queryFn: async () => (await api.get<{ data: SiteRevision[] }>(`/admin/v1/site/documents/${key}/revisions`)).data.data,
    enabled,
  })
}

export function useRestoreSiteRevision(key: string) {
  const store = useStoreDocument(key)
  return useMutation({
    mutationFn: async ({ revision, version }: { revision: number; version: number }) =>
      (await api.post<{ data: SiteDocument }>(`/admin/v1/site/documents/${key}/revisions/${revision}/restore`, { version })).data.data,
    onSuccess: store,
  })
}

export function useSiteMedia(enabled: boolean) {
  return useQuery({
    queryKey: ['site-media'],
    queryFn: async () => (await api.get<{ data: SiteMedia[] }>('/admin/v1/site/media')).data.data,
    enabled,
  })
}

export function useUploadSiteMedia() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async (file: File) => {
      const form = new FormData()
      form.append('image', file)
      return (await api.post<{ data: SiteMedia }>('/admin/v1/site/media', form, { timeout: 60000 })).data.data
    },
    onSuccess: () => qc.invalidateQueries({ queryKey: ['site-media'] }),
  })
}
