import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { api } from '@/lib/api'

// Notification template editor (B10 backend: /admin/v1/notification-templates*). Existing templates only —
// the UI never creates templates or placeholders; the server enforces the placeholder whitelist (422).
export type TemplateLocale = 'az' | 'ru' | 'en'
export const TEMPLATE_LOCALES: TemplateLocale[] = ['az', 'ru', 'en']

export interface TemplateSummary {
  id: number
  template_key: string
  category: 'security' | 'billing' | 'operational' | 'marketing'
  channels_mask: number
  is_active: boolean
  read_only: boolean
  placeholders: string[]
  locales: { locale: TemplateLocale; present: boolean }[]
  locales_complete: boolean
}
export interface TemplateLocaleCopy {
  locale: TemplateLocale
  subject: string | null
  body: string | null
  updated_by_admin_id: number | null
  updated_at: string | null
}
export interface TemplateDetail extends Omit<TemplateSummary, 'locales' | 'locales_complete'> {
  locales: TemplateLocaleCopy[]
}
export interface TemplatePreview {
  locale: TemplateLocale
  title: string
  body: string
  sample_variables: Record<string, string>
}

export const categoryLabels: Record<TemplateSummary['category'], string> = {
  security: 'Təhlükəsizlik', billing: 'Ödəniş', operational: 'Əməliyyat', marketing: 'Marketinq',
}

/** default_channels_mask bits (App\Domain\Notifications\Enums\NotificationChannel::bit). */
export const CHANNEL_BITS: { key: string; label: string; bit: number }[] = [
  { key: 'push', label: 'Push', bit: 1 },
  { key: 'sms', label: 'SMS', bit: 2 },
  { key: 'inapp', label: 'Tətbiqdaxili', bit: 4 },
  { key: 'email', label: 'E-poçt', bit: 8 },
]
export function channelsOf(mask: number) {
  return CHANNEL_BITS.filter((c) => (mask & c.bit) === c.bit)
}

export function useNotificationTemplates() {
  return useQuery({
    queryKey: ['notification-templates'],
    queryFn: async () => (await api.get<{ data: TemplateSummary[] }>('/admin/v1/notification-templates')).data.data,
  })
}

export function useNotificationTemplate(id: number) {
  return useQuery({
    queryKey: ['notification-template', id],
    queryFn: async () => (await api.get<{ data: TemplateDetail }>(`/admin/v1/notification-templates/${id}`)).data.data,
    enabled: Number.isFinite(id) && id > 0,
  })
}

export function useUpdateTemplateLocale(id: number) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: async ({ locale, subject, body }: { locale: TemplateLocale; subject: string; body: string }) =>
      (await api.put(`/admin/v1/notification-templates/${id}/locales/${locale}`, { subject, body })).data,
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['notification-template', id] })
      qc.invalidateQueries({ queryKey: ['notification-templates'] })
    },
  })
}

/** Renders a draft (or the stored copy) with sample variables — the server saves and sends nothing. */
export function usePreviewTemplate(id: number) {
  return useMutation({
    mutationFn: async (input: { locale: TemplateLocale; subject?: string; body?: string }) =>
      (await api.post<{ data: TemplatePreview }>(`/admin/v1/notification-templates/${id}/preview`, input)).data.data,
  })
}
