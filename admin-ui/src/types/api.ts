// API types — mirror the deployed Laravel resource classes 1:1 (no invented fields).

// ---- Enums (string unions matching backend enum values) ----
export type AdminRole =
  | 'super_admin' | 'technical' | 'operator' | 'finance' | 'support' | 'complex_manager'
/** A permission key (e.g. 'devices.create'). Free string — the catalog lives on the backend. */
export type Permission = string
export type DeviceStatus = 'unassigned' | 'active' | 'suspended' | 'disabled' | 'decommissioned'
export type DriverType = 'traccar' | 'ble' | 'sms'
export type SubscriptionStatus = 'pending_payment' | 'active' | 'expired' | 'cancelled' | 'refunded'
export type SubscriptionTier = 'main' | 'additional'
export type OrderStatus =
  | 'pending' | 'authorising' | 'paid' | 'failed' | 'cancelled' | 'refunded' | 'partially_refunded' | 'expired'
export type OrderPurpose = 'device_sale' | 'sub_main' | 'sub_additional' | 'sub_renewal' | 'bundle'
export type RefundStatus = 'requested' | 'processing' | 'approved' | 'rejected' | 'failed'
export type OpenCommandState = 'queued' | 'dispatching' | 'dispatched' | 'opened' | 'failed' | 'expired'
export type WhitelistAction = 'add' | 'remove' | 'clear'
export type WhitelistChangeStatus = 'pending' | 'in_progress' | 'synced' | 'failed' | 'cancelled'
export type DeviceUserRole = 'owner' | 'user'
export type DeviceUserStatus = 'active' | 'revoked'
export type DiagnosticSource = 'scheduled_ping' | 'open_dispatch' | 'admin_ping' | 'device_initiated'

// ---- Pagination ----
export interface PageInfo {
  next_cursor: string | null
  has_more: boolean
  limit: number
}
export interface Paginated<T> {
  data: T[]
  page: PageInfo
}

// ---- Auth ----
export interface AdminUser {
  id: number
  email: string
  name: string
  role: AdminRole
  complex_id: number | null
  permissions: Permission[]
  phone: string | null
  is_2fa_enabled: boolean
  status: string
  impersonator_admin_id: number | null
  last_login_at: string | null
  created_at: string | null
}
export interface LoginChallenge {
  challenge_token: string
  expires_in_seconds: number
  requires_totp: boolean
}
/** /admin/v1/auth/login response — a 2FA challenge, or a full session when Require-2FA is off. */
export interface LoginResponse {
  two_factor_required: boolean
  challenge_token?: string
  expires_in_seconds?: number
  requires_totp?: boolean
  access_token?: string
  token_type?: string
  expires_in?: number
  admin?: AdminUser
}
export interface SecuritySettings {
  require_2fa: boolean
}
export interface AdminAuthSuccess {
  access_token: string
  token_type: string
  expires_in: number
  admin: AdminUser
  used_recovery_code: boolean
  recovery_codes_remaining: number
}
export interface RecoveryCodesResult {
  codes: string[]
  generated_at: string
}

// ---- Lookups (nested) ----
export interface SimOperator {
  id: number
  code: string
  name: string
  country_iso: string
  mcc_mnc: string | null
  is_active: boolean
}
export interface DeviceModel {
  id: number
  vendor: string
  model_code: string
  supports_clip: boolean
  supports_sms: boolean
  supports_mqtt: boolean
  default_driver_type: DriverType
  whitelist_capacity: number
  sms_open_command: string | null
  is_active: boolean
}
export interface Region {
  id: number
  code: string
  name: string
}
export interface UserBrief {
  id: number
  phone_masked: string
  /** Full email — admin surface only; null for phone-only accounts. */
  email: string | null
  full_name: string | null
  /** The account was deleted from the system; its revoked roster row is kept for history. */
  deleted?: boolean
}
export interface SubscriptionBrief {
  id: number
  tier: SubscriptionTier
  status: SubscriptionStatus
  ends_at: string | null
  days_remaining: number | null
  auto_renew: boolean
}

// ---- Devices ----
export interface DeviceAdmin {
  id: number
  serial: string
  sim_phone: string | null
  sim_operator: SimOperator | null
  device_model: DeviceModel | null
  driver_type: DriverType
  status: DeviceStatus
  owner: UserBrief | null
  region: Region | null
  location_label: string | null
  image_url: string | null
  address: string | null
  latitude: number | null
  longitude: number | null
  geofence_enabled: boolean
  geofence_radius_m: number | null
  last_online_at: string | null
  online: boolean
  last_signal_strength: number | null
  whitelist_capacity_used: number
  created_at: string | null
}
export interface DeviceUser {
  id: number
  /**
   * Null-safe on purpose: a roster row survives its user being deleted from the system. The API loads
   * trashed users so this is normally populated, but the UI must never assume it — a null here used to
   * blank the whole device page.
   */
  user: UserBrief | null
  role: DeviceUserRole
  status: DeviceUserStatus
  added_at: string | null
  last_open_at: string | null
  subscription: SubscriptionBrief | null
}
export interface DeviceAdminDetail extends DeviceAdmin {
  users: DeviceUser[]
  metadata: Record<string, unknown>
}

// ---- Subscriptions ----
export interface Subscription {
  id: number
  tier: SubscriptionTier
  status: SubscriptionStatus
  ends_at: string | null
  days_remaining: number | null
  auto_renew: boolean
  device_id: number | null
  user_id: number | null
  starts_at: string | null
  price_minor: number
  currency: string
  term_days: number
  last_reminder_kind: string | null
  last_reminder_sent_at: string | null
}

// ---- Orders / Refunds ----
export interface OrderItem {
  id: number
  item_type: string
  referenced_id: number | null
  description: string | null
  quantity: number
  unit_amount_minor: number
  total_amount_minor: number
}
export interface Order {
  id: number
  reference: string
  status: OrderStatus
  purpose: OrderPurpose
  amount_minor: number
  currency: string
  bank_order_id?: string | null
  bank_redirect_url: string | null
  expires_at: string | null
  paid_at: string | null
  failed_at: string | null
  failed_reason: string | null
  created_at: string | null
  updated_at?: string | null
  // Admin list/detail enrichment (present when payer/payments are eager-loaded server-side)
  customer?: string | null
  captured_minor?: number
  refunded_minor?: number
  refundable_minor?: number
  items?: OrderItem[]
}
export interface Refund {
  id: number
  order_id: number
  amount_minor: number
  reason: string | null
  status: RefundStatus
  requested_by_admin_id: number
  processed_by_admin_id: number | null
  linked_payment_id: number | null
  error_message: string | null
  created_at: string | null
}

// ---- DeviceComm ----
export type CommandDirection = 'open' | 'close'
export type OpenCommandSource = 'mobile' | 'admin' | 'automation'

export interface OpenCommand {
  id: number
  device_id: number
  state: OpenCommandState
  direction: CommandDirection | null
  source: OpenCommandSource
  failure_reason: string | null
  driver: DriverType
  command_text: string | null
  terminal_response: string | null
  requested_at: string | null
  dispatched_at: string | null
  completed_at: string | null
  latency_ms: number | null
  attempts: number
}
export interface DeviceDiagnostic {
  id: number
  device_id: number
  source: DiagnosticSource
  online: boolean
  signal_strength: number | null
  battery_level: number | null
  firmware_version: string | null
  reported_at: string | null
}
export interface WhitelistChange {
  id: number
  device_id: number
  action: WhitelistAction
  phone: string
  status: WhitelistChangeStatus
  attempt_count: number
  last_error: string | null
  last_attempt_at: string | null
  next_attempt_at: string | null
  synced_at: string | null
  created_at: string | null
}

// ---- Request payloads ----
export interface CreateDeviceInput {
  serial: string
  device_model_id: number
  sim_phone: string
  driver_type: DriverType
  sim_operator_id?: number | null
  sim_iccid?: string | null
  firmware_version?: string | null
  region_id?: number | null
  location_label?: string | null
  image_url?: string | null
  address?: string | null
  latitude?: number | null
  longitude?: number | null
}
export type UpdateDeviceInput = Partial<Omit<CreateDeviceInput, 'serial' | 'device_model_id'>> & {
  // GEOFENCE-2 (admin-only, edit flow) — sent to PATCH /admin/v1/devices/{id}. radius is null when off.
  geofence_enabled?: boolean
  geofence_radius_m?: number | null
}
export interface TransferInput {
  new_owner_phone: string
  reason: string
  keep_existing_users?: boolean
}

// ---- Visitor links ----
export type VisitorAccessType = 'one_time' | 'time_limited'
export type VisitorLinkStatus = 'active' | 'expired' | 'revoked' | 'used_up'
export type VisitorPurpose = 'guest' | 'delivery' | 'courier' | 'service' | 'cleaning' | 'taxi' | 'other'
export type VisitorUsageResult =
  | 'accepted'
  | 'expired'
  | 'revoked'
  | 'usage_exceeded'
  | 'device_inactive'
  | 'not_found'

export interface VisitorLinkCreatedBy {
  type: 'admin' | 'resident'
  id: number | null
  label: string | null
}
export interface VisitorLinkDeviceBrief {
  id: number
  serial: string
  label: string
}
export interface VisitorLinkAdmin {
  id: number
  visitor_name: string | null
  purpose: VisitorPurpose | null
  access_type: VisitorAccessType
  status: VisitorLinkStatus
  expires_at: string | null
  max_usage: number | null
  usage_count: number
  last_used_at: string | null
  revoked_at: string | null
  created_at: string | null
  token_prefix: string | null
  device: VisitorLinkDeviceBrief | null
  created_by: VisitorLinkCreatedBy
}
export interface VisitorLinkUsage {
  id: number
  used_at: string | null
  result: VisitorUsageResult
  ip: string | null
  user_agent: string | null
}
export interface CreateVisitorLinkInput {
  access_type: VisitorAccessType
  duration_minutes?: number | null
  visitor_name?: string | null
  purpose?: VisitorPurpose | null
}
export interface CreateVisitorLinkResponse {
  link: VisitorLinkAdmin
  token: string
  url: string
}

// ---- Notification campaigns (admin-initiated) ----
export type CampaignStatus = 'draft' | 'queued' | 'sending' | 'sent' | 'failed'
export type AudienceScope = 'all_users' | 'user_ids' | 'filter' | 'complex'
export type CampaignLanguage = 'az' | 'ru' | 'en'

export interface AudienceFilter {
  q?: string
  complex_id?: number
  role?: string
  subscription_status?: string
}
export interface AudienceSpec {
  scope: AudienceScope
  user_ids?: number[]
  filter?: AudienceFilter
  complex_id?: number
}
export interface AudiencePreview {
  recipient_count: number
}
export interface NotificationCampaign {
  id: number
  type: 'system'
  title: string
  body: string
  language: CampaignLanguage
  audience_scope: AudienceScope
  status: CampaignStatus
  total_recipients: number | null
  sent_count: number
  failed_count: number
  created_by_admin_id: number
  confirmed_at: string | null
  sent_at: string | null
  created_at: string
}
export interface NotificationCampaignCreate {
  type: 'system'
  title: string
  body: string
  language: CampaignLanguage
  audience: AudienceSpec
  confirmed: boolean
}
