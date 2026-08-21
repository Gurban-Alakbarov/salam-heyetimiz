import type { VisitorAccessType, VisitorPurpose } from '@/types/api'

// Azerbaijani labels for visitor-link enums — mirror the backend/Flutter wording so the whole product
// speaks the same language. Kept in one place and reused by the panel, dialog and details drawer.

export const PURPOSE_LABELS: Record<VisitorPurpose, string> = {
  guest: 'Qonaq',
  delivery: 'Çatdırılma',
  courier: 'Kuryer',
  service: 'Xidmət',
  cleaning: 'Təmizlik',
  taxi: 'Taksi',
  other: 'Digər',
}

export const ACCESS_TYPE_LABELS: Record<VisitorAccessType, string> = {
  one_time: 'Birdəfəlik',
  time_limited: 'Müddətli',
}

export const PURPOSE_OPTIONS = (Object.keys(PURPOSE_LABELS) as VisitorPurpose[]).map((value) => ({
  value,
  label: PURPOSE_LABELS[value],
}))

export const ACCESS_TYPE_OPTIONS = (Object.keys(ACCESS_TYPE_LABELS) as VisitorAccessType[]).map((value) => ({
  value,
  label: ACCESS_TYPE_LABELS[value],
}))

// Duration presets for time-limited links (minutes) — capped at the backend max (12h).
export const DURATION_OPTIONS: { value: number; label: string }[] = [
  { value: 15, label: '15 dəqiqə' },
  { value: 30, label: '30 dəqiqə' },
  { value: 60, label: '1 saat' },
  { value: 120, label: '2 saat' },
  { value: 240, label: '4 saat' },
  { value: 480, label: '8 saat' },
  { value: 720, label: '12 saat' },
]

export function purposeLabel(purpose: VisitorPurpose | null): string {
  return purpose ? PURPOSE_LABELS[purpose] : '—'
}

export function accessTypeLabel(type: VisitorAccessType): string {
  return ACCESS_TYPE_LABELS[type]
}

/** "3 / 1" style usage cell — used / max (∞ for unlimited time-limited links). */
export function usageLabel(usageCount: number, maxUsage: number | null): string {
  return `${usageCount} / ${maxUsage ?? '∞'}`
}
