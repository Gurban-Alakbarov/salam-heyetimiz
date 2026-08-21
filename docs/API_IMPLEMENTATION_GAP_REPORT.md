# Salam Həyətimiz — API Implementation Gap Report

**Version:** 1.0
**Date:** 2026-06-13
**Compared:** [openapi/v1.yaml](openapi/v1.yaml) v1.1.0 (the binding contract, **100 operations** — confirmed by `php docs/openapi/validate.php`) against the current codebase (routes + controllers).
**No code was modified to produce this report.**

## Classification criteria

| Status | Definition |
|---|---|
| **Implemented** | A route is registered AND a controller action handles it AND its request/response conform to the OpenAPI operation. |
| **Partially implemented** | A route/controller exists but is incomplete (missing fields, validation, error cases, or response shape divergence). |
| **Not implemented** | No route and no controller action for the operationId. (The data layer — model/migration — may still exist; that is reported separately as "Data foundation".) |

A separate **Data foundation** column states whether the endpoint's underlying tables/models already exist (batches 00–04 = ✅ ready) or await a later batch (⏳ pending, with the batch number). This measures readiness to implement; it does **not** change the endpoint status.

## Totals

| Status | Count | % |
|---|---|---|
| Implemented | **2** | 2% |
| Partially implemented | **0** | 0% |
| Not implemented | **98** | 98% |
| **Total operations** | **100** | 100% |

### By surface

| Surface | Total | Implemented | Partial | Not implemented |
|---|---|---|---|---|
| Mobile / public / webhooks / technical | 49 | 2 | 0 | 47 |
| Admin (`/admin/v1`) | 51 | 0 | 0 | 51 |

### Data-foundation readiness of the *not-implemented* set

| Foundation | Endpoints | Note |
|---|---|---|
| ✅ Ready (tables in 00–04) | ~31 | Auth, Profile, Devices, Roster, most Admin Users/Admins/Devices/Lookups, Privacy consents — data layer exists; only HTTP layer missing. |
| ⏳ Pending (batch ≥ 05) | ~67 | Commands (07), Subscriptions (06), Orders/Payments/Refunds (05), Notifications (08), Audit/Settings/Flags/DSR (09), Reports/Stats (10). |

> Interpretation: this commit deliberately delivered the **foundations + data layer (batches 00–04)** and the public health probes only. The HTTP/business layer for all domain endpoints is scheduled for later increments, gated on their data batches. The 2% endpoint coverage is therefore expected and on-plan, not a defect.

---

## 1. Mobile / public / webhooks / technical

### 1.1 Health — `Health`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| getHealthLive | GET | `/v1/health/live` | ✅ Implemented | n/a |
| getHealthReady | GET | `/v1/health/ready` | ✅ Implemented | n/a (checks DB+Redis) |

### 1.2 Auth — `Auth`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| requestOtp | POST | `/v1/auth/otp/request` | ❌ Not implemented | ✅ `otps`, rate-limiter `otp-request` defined |
| verifyOtp | POST | `/v1/auth/otp/verify` | ❌ Not implemented | ✅ `otps`, `refresh_tokens`, `user_devices` |
| refreshToken | POST | `/v1/auth/refresh` | ❌ Not implemented | ✅ `refresh_tokens` |
| logout | POST | `/v1/auth/logout` | ❌ Not implemented | ✅ `refresh_tokens` |
| getJwks | GET | `/.well-known/jwks.json` | ❌ Not implemented | ⏳ key infra (env paths defined; JWKS service later) |

### 1.3 Profile — `Profile`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| getMe | GET | `/v1/me` | ❌ Not implemented | ✅ `users` |
| updateMe | PATCH | `/v1/me` | ❌ Not implemented | ✅ `users` |
| deleteMe | DELETE | `/v1/me` | ❌ Not implemented | ✅ `users` (successor check needs subs → ⏳ 06) |
| enrollBiometrics | POST | `/v1/me/biometrics/enroll` | ❌ Not implemented | ✅ `user_devices` |
| disableBiometrics | DELETE | `/v1/me/biometrics` | ❌ Not implemented | ✅ `user_devices` |

### 1.4 Devices — `Devices`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| listMyDevices | GET | `/v1/devices` | ❌ Not implemented | ✅ `devices`, `device_users` |
| getDevice | GET | `/v1/devices/{deviceId}` | ❌ Not implemented | ✅ `devices` |
| getDeviceStats | GET | `/v1/devices/{deviceId}/stats` | ❌ Not implemented | ⏳ `device_daily_stats` (10) |

### 1.5 Commands — `Commands`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| openDevice | POST | `/v1/devices/{deviceId}/open` | ❌ Not implemented | ⏳ `open_commands`/`_attempts` (07); rate-limiter `open` defined |
| listDeviceCommands | GET | `/v1/devices/{deviceId}/commands` | ❌ Not implemented | ⏳ `open_commands` (07) |
| getCommand | GET | `/v1/commands/{commandId}` | ❌ Not implemented | ⏳ `open_commands` (07) |
| submitOpenFeedback | POST | `/v1/commands/{commandId}/feedback` | ❌ Not implemented | ⏳ `open_command_feedback` (07) |

### 1.6 Roster — `Roster`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| listDeviceRoster | GET | `/v1/devices/{deviceId}/users` | ❌ Not implemented | ✅ `device_users` |
| revokeRosterUser | DELETE | `/v1/devices/{deviceId}/users/{userId}` | ❌ Not implemented | ✅ `device_users`, `device_user_history` |

### 1.7 Invitations — `Invitations`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| createInvitation | POST | `/v1/devices/{deviceId}/invitations` | ❌ Not implemented | ✅ `invitations` (owner-pays order → ⏳ 05) |
| listDeviceInvitations | GET | `/v1/devices/{deviceId}/invitations` | ❌ Not implemented | ✅ `invitations` |
| getInvitationByToken | GET | `/v1/invitations/{token}` | ❌ Not implemented | ✅ `invitations` |
| acceptInvitation | POST | `/v1/invitations/{token}/accept` | ❌ Not implemented | ✅ `invitations`, `device_users` |
| declineInvitation | POST | `/v1/invitations/{token}/decline` | ❌ Not implemented | ✅ `invitations` |

### 1.8 Subscriptions — `Subscriptions`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| listMySubscriptions | GET | `/v1/subscriptions` | ❌ Not implemented | ⏳ `subscriptions` (06) |
| getSubscription | GET | `/v1/subscriptions/{subscriptionId}` | ❌ Not implemented | ⏳ `subscriptions`/`_periods` (06) |
| renewSubscription | POST | `/v1/subscriptions/{subscriptionId}/renew` | ❌ Not implemented | ⏳ (06) + orders (05) |
| toggleAutoRenew | PATCH | `/v1/subscriptions/{subscriptionId}/auto-renew` | ❌ Not implemented | ⏳ (06) |

### 1.9 Orders & Payments — `Orders & Payments`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| listMyOrders | GET | `/v1/orders` | ❌ Not implemented | ⏳ `orders` (05) |
| createOrder | POST | `/v1/orders` | ❌ Not implemented | ⏳ `orders`/`order_items` (05) |
| getOrder | GET | `/v1/orders/{orderId}` | ❌ Not implemented | ⏳ `orders` (05) |
| recheckOrder | POST | `/v1/orders/{orderId}/recheck` | ❌ Not implemented | ⏳ `orders` (05) |

### 1.10 Notifications — `Notifications`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| listNotifications | GET | `/v1/notifications` | ❌ Not implemented | ⏳ `notifications` (08) |
| markNotificationRead | POST | `/v1/notifications/{notificationId}/read` | ❌ Not implemented | ⏳ (08) |
| markAllNotificationsRead | POST | `/v1/notifications/read-all` | ❌ Not implemented | ⏳ (08) |
| getNotificationSettings | GET | `/v1/notifications/settings` | ❌ Not implemented | ⏳ `user_notification_settings` (08) |
| updateNotificationSettings | PATCH | `/v1/notifications/settings` | ❌ Not implemented | ⏳ (08) |
| upsertPushToken | PUT | `/v1/notifications/push-token` | ❌ Not implemented | ✅ `user_devices` |

### 1.11 Privacy — `Privacy`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| recordConsent | POST | `/v1/consents` | ❌ Not implemented | ✅ `user_consents` |
| listMyConsents | GET | `/v1/consents` | ❌ Not implemented | ✅ `user_consents` |
| requestDataExport | POST | `/v1/privacy/export` | ❌ Not implemented | ⏳ `data_subject_requests` (09) |
| requestDataDeletion | POST | `/v1/privacy/deletion` | ❌ Not implemented | ⏳ `data_subject_requests` (09) |

### 1.12 Technical mobile mode — `Devices` (admin-role JWT)

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| techRegisterDevice | POST | `/v1/technical/devices` | ❌ Not implemented | ✅ `devices` |
| techAssignDevice | POST | `/v1/technical/devices/{deviceId}/assign` | ❌ Not implemented | ✅ `devices`, `device_users` |
| techDiagnosticsPing | POST | `/v1/technical/devices/{deviceId}/diagnostics/ping` | ❌ Not implemented | ⏳ `device_diagnostics` (07) |

### 1.13 Webhooks — `Webhooks`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| paymentCallback | POST | `/v1/payments/callback` | ❌ Not implemented | ⏳ `payment_callbacks`/`orders` (05); `webhook` group + `CaptureRawBody` slot reserved |
| smsInbound | POST | `/v1/sms/inbound` | ❌ Not implemented | ⏳ `open_commands` correlation (07) |

---

## 2. Admin (`/admin/v1`)

> All 51 admin operations are **Not implemented** (the admin route group exists but registers no endpoints yet; `auth.admin` guard lands in the Auth increment). Foundation readiness varies by domain.

### 2.1 Admin Auth — `Admin / Auth`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminLogin | POST | `/admin/v1/auth/login` | ❌ Not implemented | ✅ `admin_users` |
| adminVerify2fa | POST | `/admin/v1/auth/2fa/verify` | ❌ Not implemented | ✅ `admin_users` (TOTP + `recovery_codes_hashes`) |
| adminLogout | POST | `/admin/v1/auth/logout` | ❌ Not implemented | ✅ `admin_users` |
| adminMe | GET | `/admin/v1/auth/me` | ❌ Not implemented | ✅ `admin_users` |
| regenerateRecoveryCodes | POST | `/admin/v1/auth/recovery-codes` | ❌ Not implemented | ✅ `admin_users.recovery_codes_hashes` |

### 2.2 Admin Dashboard — `Admin / Dashboard`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminMetricsOverview | GET | `/admin/v1/metrics/overview` | ❌ Not implemented | ⏳ `*_daily_stats` (10) |

### 2.3 Admin Users — `Admin / Users`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListUsers | GET | `/admin/v1/users` | ❌ Not implemented | ✅ `users` |
| adminGetUser | GET | `/admin/v1/users/{userId}` | ❌ Not implemented | ✅ `users` |
| adminBlockUser | POST | `/admin/v1/users/{userId}/block` | ❌ Not implemented | ✅ `users` |
| adminUnblockUser | POST | `/admin/v1/users/{userId}/unblock` | ❌ Not implemented | ✅ `users` |

### 2.4 Admin Admins — `Admin / Admins`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListAdmins | GET | `/admin/v1/admins` | ❌ Not implemented | ✅ `admin_users` |
| adminCreateAdmin | POST | `/admin/v1/admins` | ❌ Not implemented | ✅ `admin_users` |
| adminUpdateAdmin | PATCH | `/admin/v1/admins/{adminId}` | ❌ Not implemented | ✅ `admin_users` |
| adminOffboardAdmin | DELETE | `/admin/v1/admins/{adminId}` | ❌ Not implemented | ✅ `admin_users` |

### 2.5 Admin Devices — `Admin / Devices`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListDevices | GET | `/admin/v1/devices` | ❌ Not implemented | ✅ `devices` |
| adminCreateDevice | POST | `/admin/v1/devices` | ❌ Not implemented | ✅ `devices` |
| adminGetDevice | GET | `/admin/v1/devices/{deviceId}` | ❌ Not implemented | ✅ `devices` |
| adminUpdateDevice | PATCH | `/admin/v1/devices/{deviceId}` | ❌ Not implemented | ✅ `devices` |
| adminDecommissionDevice | DELETE | `/admin/v1/devices/{deviceId}` | ❌ Not implemented | ✅ `devices` |
| adminResyncWhitelist | POST | `/admin/v1/devices/{deviceId}/whitelist/resync` | ❌ Not implemented | ⏳ `whitelist_changes` (07) |
| adminDisableDevice | POST | `/admin/v1/devices/{deviceId}/disable` | ❌ Not implemented | ✅ `devices` |
| adminEnableDevice | POST | `/admin/v1/devices/{deviceId}/enable` | ❌ Not implemented | ✅ `devices` |
| adminTransferDevice | POST | `/admin/v1/devices/{deviceId}/transfer` | ❌ Not implemented | ✅ `devices`, `device_users` |
| adminDeviceCommands | GET | `/admin/v1/devices/{deviceId}/commands` | ❌ Not implemented | ⏳ `open_commands` (07) |
| adminDeviceDiagnostics | GET | `/admin/v1/devices/{deviceId}/diagnostics` | ❌ Not implemented | ⏳ `device_diagnostics` (07) |
| adminWhitelistQueue | GET | `/admin/v1/devices/{deviceId}/whitelist-queue` | ❌ Not implemented | ⏳ `whitelist_changes` (07) |

### 2.6 Admin Lookups — `Admin / Lookups`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListSimOperators | GET | `/admin/v1/lookups/sim-operators` | ❌ Not implemented | ✅ `sim_operators` |
| adminListDeviceModels | GET | `/admin/v1/lookups/device-models` | ❌ Not implemented | ✅ `device_models` |
| adminListRegions | GET | `/admin/v1/lookups/regions` | ❌ Not implemented | ✅ `regions` |

### 2.7 Admin Orders — `Admin / Orders`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListOrders | GET | `/admin/v1/orders` | ❌ Not implemented | ⏳ `orders` (05) |
| adminGetOrder | GET | `/admin/v1/orders/{orderId}` | ❌ Not implemented | ⏳ `orders`/`payments` (05) |
| adminRefundOrder | POST | `/admin/v1/orders/{orderId}/refund` | ❌ Not implemented | ⏳ `refunds`/`payments` (05) |
| adminRecheckOrder | POST | `/admin/v1/orders/{orderId}/recheck` | ❌ Not implemented | ⏳ `orders` (05) |

### 2.8 Admin Refunds — `Admin / Refunds`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListRefunds | GET | `/admin/v1/refunds` | ❌ Not implemented | ⏳ `refunds` (05) |

### 2.9 Admin Subscriptions — `Admin / Subscriptions`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListSubscriptions | GET | `/admin/v1/subscriptions` | ❌ Not implemented | ⏳ `subscriptions` (06) |

### 2.10 Admin Reports — `Admin / Reports`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminReportsRevenue | GET | `/admin/v1/reports/revenue` | ❌ Not implemented | ⏳ `revenue_daily_stats` (10) |
| adminReportsDevices | GET | `/admin/v1/reports/devices` | ❌ Not implemented | ⏳ `device_daily_stats` (10) |
| adminReportsSubscriptions | GET | `/admin/v1/reports/subscriptions` | ❌ Not implemented | ⏳ `subscription_daily_stats` (10) |
| adminListReportJobs | GET | `/admin/v1/report-jobs` | ❌ Not implemented | ⏳ `report_jobs` (09) |
| adminCreateReportJob | POST | `/admin/v1/report-jobs` | ❌ Not implemented | ⏳ `report_jobs` (09) |
| adminGetReportJob | GET | `/admin/v1/report-jobs/{jobId}` | ❌ Not implemented | ⏳ `report_jobs` (09) |

### 2.11 Admin Audit — `Admin / Audit`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminAuditSearch | GET | `/admin/v1/audit` | ❌ Not implemented | ⏳ `audit_log` (09) |

### 2.12 Admin Settings — `Admin / Settings`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListSettings | GET | `/admin/v1/settings` | ❌ Not implemented | ⏳ `settings` (09) |
| adminUpdateSetting | PATCH | `/admin/v1/settings/{key}` | ❌ Not implemented | ⏳ `settings` (09) |

### 2.13 Admin Feature Flags — `Admin / Feature Flags`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListFeatureFlags | GET | `/admin/v1/feature-flags` | ❌ Not implemented | ⏳ `feature_flags` (09) |
| adminUpdateFeatureFlag | PATCH | `/admin/v1/feature-flags/{key}` | ❌ Not implemented | ⏳ `feature_flags` (09) |

### 2.14 Admin Notification Templates — `Admin / Notification Templates`

| operationId | Method | Path | Status | Data foundation |
|---|---|---|---|---|
| adminListNotificationTemplates | GET | `/admin/v1/notification-templates` | ❌ Not implemented | ⏳ `notification_templates` (08) |
| adminGetNotificationTemplate | GET | `/admin/v1/notification-templates/{key}` | ❌ Not implemented | ⏳ (08) |
| adminUpdateNotificationTemplate | PATCH | `/admin/v1/notification-templates/{key}` | ❌ Not implemented | ⏳ (08) |
| adminUpsertNotificationTemplateLocale | PUT | `/admin/v1/notification-templates/{key}/locales/{locale}` | ❌ Not implemented | ⏳ `notification_template_locales` (08) |
| adminPreviewNotificationTemplate | POST | `/admin/v1/notification-templates/{key}/preview` | ❌ Not implemented | ⏳ (08) |

---

## 3. Reconciliation

- **Operation count:** 49 (non-admin) + 51 (admin) = **100** ✓ matches `validate.php`.
- **Implemented:** `getHealthLive`, `getHealthReady` = **2**.
- **Partially implemented:** **0** (no half-built endpoints — the codebase has either a complete endpoint or none).
- **Not implemented:** **98**.
- **Contract integrity:** the OpenAPI itself is valid and unchanged; this report measures only code coverage against it.

*End of API Implementation Gap Report v1.0.*
