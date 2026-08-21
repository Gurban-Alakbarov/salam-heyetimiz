import { Badge, type BadgeProps } from '@/components/ui/badge'

type Variant = NonNullable<BadgeProps['variant']>
interface Meta {
  label: string
  variant: Variant
}

const deviceStatus: Record<string, Meta> = {
  unassigned: { label: 'Təyin olunmayıb', variant: 'muted' },
  active: { label: 'Aktiv', variant: 'success' },
  suspended: { label: 'Dayandırılıb', variant: 'warning' },
  disabled: { label: 'Bağlı', variant: 'destructive' },
  decommissioned: { label: 'Çıxarılıb', variant: 'muted' },
}

const subscriptionStatus: Record<string, Meta> = {
  pending_payment: { label: 'Ödəniş gözləyir', variant: 'warning' },
  active: { label: 'Aktiv', variant: 'success' },
  expired: { label: 'Bitib', variant: 'muted' },
  cancelled: { label: 'Ləğv edilib', variant: 'destructive' },
  refunded: { label: 'Geri qaytarılıb', variant: 'secondary' },
}

const orderStatus: Record<string, Meta> = {
  pending: { label: 'Gözləyir', variant: 'warning' },
  authorising: { label: 'Təsdiqlənir', variant: 'warning' },
  paid: { label: 'Ödənilib', variant: 'success' },
  failed: { label: 'Uğursuz', variant: 'destructive' },
  cancelled: { label: 'Ləğv edilib', variant: 'muted' },
  refunded: { label: 'Geri qaytarılıb', variant: 'secondary' },
  partially_refunded: { label: 'Qismən qaytarılıb', variant: 'secondary' },
  expired: { label: 'Vaxtı keçib', variant: 'muted' },
}

const refundStatus: Record<string, Meta> = {
  requested: { label: 'Sorğulanıb', variant: 'warning' },
  processing: { label: 'İcra olunur', variant: 'warning' },
  approved: { label: 'Təsdiqlənib', variant: 'success' },
  rejected: { label: 'Rədd edilib', variant: 'destructive' },
  failed: { label: 'Uğursuz', variant: 'destructive' },
}

const commandState: Record<string, Meta> = {
  queued: { label: 'Növbədə', variant: 'muted' },
  dispatching: { label: 'Göndərilir', variant: 'warning' },
  dispatched: { label: 'Göndərilib', variant: 'secondary' },
  opened: { label: 'Açılıb', variant: 'success' },
  failed: { label: 'Uğursuz', variant: 'destructive' },
  expired: { label: 'Vaxtı keçib', variant: 'muted' },
}

const whitelistStatus: Record<string, Meta> = {
  pending: { label: 'Gözləyir', variant: 'warning' },
  in_progress: { label: 'İcra olunur', variant: 'warning' },
  synced: { label: 'Sinxron', variant: 'success' },
  failed: { label: 'Uğursuz', variant: 'destructive' },
  cancelled: { label: 'Ləğv edilib', variant: 'muted' },
}

const visitorStatus: Record<string, Meta> = {
  active: { label: 'Aktiv', variant: 'success' },
  expired: { label: 'Vaxtı bitib', variant: 'muted' },
  revoked: { label: 'Ləğv edilib', variant: 'destructive' },
  used_up: { label: 'İstifadə olunub', variant: 'secondary' },
}

const campaignStatus: Record<string, Meta> = {
  draft: { label: 'Qaralama', variant: 'muted' },
  queued: { label: 'Növbədə', variant: 'warning' },
  sending: { label: 'Göndərilir', variant: 'warning' },
  sent: { label: 'Göndərilib', variant: 'success' },
  failed: { label: 'Uğursuz', variant: 'destructive' },
}

const visitorUsageResult: Record<string, Meta> = {
  accepted: { label: 'Qəbul edildi', variant: 'success' },
  expired: { label: 'Vaxtı bitib', variant: 'muted' },
  revoked: { label: 'Ləğv edilib', variant: 'destructive' },
  usage_exceeded: { label: 'Limit aşıldı', variant: 'destructive' },
  device_inactive: { label: 'Cihaz aktiv deyil', variant: 'warning' },
  not_found: { label: 'Tapılmadı', variant: 'muted' },
}

const registry = {
  device: deviceStatus,
  subscription: subscriptionStatus,
  order: orderStatus,
  refund: refundStatus,
  command: commandState,
  whitelist: whitelistStatus,
  visitor: visitorStatus,
  visitorUsage: visitorUsageResult,
  campaign: campaignStatus,
} as const

export type StatusKind = keyof typeof registry

export function StatusBadge({ kind, value }: { kind: StatusKind; value: string }) {
  const meta = registry[kind][value]
  if (!meta) return <Badge variant="muted">{value}</Badge>
  return <Badge variant={meta.variant}>{meta.label}</Badge>
}
