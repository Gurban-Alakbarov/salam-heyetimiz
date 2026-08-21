import { format, formatDistanceToNow, parseISO } from 'date-fns'

export function formatMoney(minor: number, currency = 'AZN'): string {
  try {
    return new Intl.NumberFormat('az-AZ', {
      style: 'currency',
      currency,
      minimumFractionDigits: 2,
    }).format(minor / 100)
  } catch {
    return `${(minor / 100).toFixed(2)} ${currency}`
  }
}

export function formatDateTime(iso?: string | null): string {
  if (!iso) return '—'
  try {
    return format(parseISO(iso), 'dd.MM.yyyy HH:mm')
  } catch {
    return '—'
  }
}

export function formatDate(iso?: string | null): string {
  if (!iso) return '—'
  try {
    return format(parseISO(iso), 'dd.MM.yyyy')
  } catch {
    return '—'
  }
}

export function relativeTime(iso?: string | null): string {
  if (!iso) return '—'
  try {
    return formatDistanceToNow(parseISO(iso), { addSuffix: true })
  } catch {
    return '—'
  }
}

export function signalLabel(strength: number | null): string {
  if (strength === null) return '—'
  if (strength >= 20) return `${strength}/31 (güclü)`
  if (strength >= 10) return `${strength}/31 (orta)`
  return `${strength}/31 (zəif)`
}
