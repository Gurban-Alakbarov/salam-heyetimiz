# API Ground Truth (verified against the deployed backend, 2026-06-21)

> Internal shared reference for documentation generation. Verified from production `route:list` +
> `docs/openapi/v1.yaml`. **Use this as the authoritative list of what is IMPLEMENTED.** The OpenAPI
> spec describes 100 operations (full contract); only the endpoints below are actually deployed.

## Base URLs (PRODUCTION — real deployment)
- Mobile API: `https://salamheyetimiz.com/v1`
- Admin API: `https://salamheyetimiz.com/admin/v1`
- JWKS (admin token verification): `https://salamheyetimiz.com/.well-known/jwks.json`
- Health: `https://salamheyetimiz.com/v1/health/{live,ready}`

> Note: `docs/openapi/v1.yaml` `servers:` list `api.salamhayetimiz.az` — that is the spec's aspirational
> hostname. The REAL deployed host is `salamheyetimiz.com` (same host serves `/v1` and `/admin/v1`).

## Auth model
- Two JWT bearer audiences: **userBearerAuth** (mobile) and **adminBearerAuth** (admin). RS256, `kid` header.
- Mobile access token TTL **15 min**; admin **30 min**; opaque refresh token TTL **60 days** (rotated each use).
- Header on authed calls: `Authorization: Bearer <access_token>`.

### Mobile auth flow (OTP)
1. `POST /v1/auth/otp/request` `{ phone }` → 202 (OTP sent; unknown phone auto-registers — R-DOM-01).
2. `POST /v1/auth/otp/verify` `{ phone, code, device:{ install_uuid, platform } }` → 200 `AuthSuccess`.
3. Use `Authorization: Bearer access_token`. Refresh: `POST /v1/auth/refresh` `{ refresh_token }` → `AuthSuccess`.
4. `POST /v1/auth/logout` (authed) revokes the refresh family.
- `AuthSuccess = { access_token, refresh_token, token_type:"Bearer", expires_in, user }`.
- Biometrics: `POST /v1/me/biometrics/enroll`, `DELETE /v1/me/biometrics`.

### Admin auth flow (password + 2FA)
1. `POST /admin/v1/auth/login` `{ email, password }` → either `AdminAuthSuccess` OR a **challenge** (`{ challenge_token, expires_in_seconds }`) when 2FA is enabled.
2. `POST /admin/v1/auth/2fa/verify` `{ challenge_token, code }` (TOTP or recovery code) → `AdminAuthSuccess` (tfa_verified=true).
3. `GET /admin/v1/auth/me`, `POST /admin/v1/auth/logout`, `POST /admin/v1/auth/recovery-codes` (requires tfa_verified).

## Conventions
- **Error envelope** (all 4xx/5xx): `{ "error": { "code", "message_key", "message", "details": {}|null, "request_id" } }`. `message` is localized (az). `code` is the stable machine key (e.g. `subscription_required`, `cooldown`, `device_disabled`, `rate_limited`, `validation_error`).
- **Validation errors**: 422 `ValidationErrorEnvelope` (per-field).
- **Cursor pagination**: list responses → `{ data: [...], page: { next_cursor: string|null, has_more: bool, limit: int } }`. Query: `?limit=25&cursor=<opaque>`.
- **Idempotency-Key** (header): accepted on all mutating endpoints; **REQUIRED** on `openDevice`, `createOrder`, `renewSubscription` (server rejects on absence). Same key replays the same result.
- **Localization**: `Accept-Language: az|ru|en` (default az). All requests `Accept: application/json`.
- **Rate limiting**: 429 `rate_limited` + `Retry-After` (seconds). Cooldown on open → 429 `cooldown` + `Retry-After`.
- **Open command lifecycle** (R-GSM-06): `queued → dispatching → dispatched/opened/failed/expired`. Traccar confirms actuation (`opened`); SMS terminates at `dispatched`. Mobile gets `expected_completion_ms`, `driver_confirms_actuation`, `websocket_channel` (`private-user.{id}`) in the open response. Real-time push (Reverb) is **deferred** → poll `GET /v1/commands/{commandId}` (R-ARCH-12 fallback).

## IMPLEMENTED mobile endpoints (`/v1`)
| Method | Path | operationId | Auth | Notes |
|---|---|---|---|---|
| POST | /auth/otp/request | requestOtp | public | `{phone}` → 202 |
| POST | /auth/otp/verify | verifyOtp | public | → AuthSuccess |
| POST | /auth/refresh | refreshToken | public | `{refresh_token}` |
| POST | /auth/logout | logout | user | |
| POST | /me/biometrics/enroll | enrollBiometrics | user | |
| DELETE | /me/biometrics | disableBiometrics | user | |
| GET | /devices | listMyDevices | user | filter `?filter=all/owned/member`; per-caller role/can_open |
| GET | /devices/{deviceId} | getDevice | user | |
| GET | /devices/{deviceId}/stats | getDeviceStats | user | |
| POST | /devices/{deviceId}/open | openDevice | user | **Idempotency-Key required**; 202; cooldown→429 |
| GET | /devices/{deviceId}/commands | listDeviceCommands | user | cursor |
| GET | /commands/{commandId} | getCommand | user | poll for state |
| POST | /commands/{commandId}/feedback | submitOpenFeedback | user | did gate open? |
| GET | /subscriptions | listMySubscriptions | user | |
| GET | /subscriptions/{subscriptionId} | getSubscription | user | |
| POST | /subscriptions/{subscriptionId}/renew | renewSubscription | user | **Idempotency-Key**; 200 (creates order) |
| PATCH | /subscriptions/{subscriptionId}/auto-renew | toggleAutoRenew | user | `{auto_renew}`; enable may 409 |
| GET | /orders | listMyOrders | user | |
| POST | /orders | createOrder | user | **Idempotency-Key**; returns payment redirect |
| GET | /orders/{orderId} | getOrder | user | |
| POST | /orders/{orderId}/recheck | recheckOrder | user | reconcile vs bank |
| POST | /technical/devices | techRegisterDevice | admin(tech/super) | mobile host, admin JWT |
| POST | /technical/devices/{deviceId}/assign | techAssignDevice | admin(tech/super) | |
| GET | /health/live, /health/ready | getHealthLive/Ready | public | |
| POST | /payments/callback | paymentCallback | HMAC (Kapital) | webhook, not for clients |
| POST | /traccar/forward | traccarForward | shared token | internal Traccar webhook |

## IMPLEMENTED admin endpoints (`/admin/v1`)
| Method | Path | operationId |
|---|---|---|
| POST | /auth/login | adminLogin |
| POST | /auth/2fa/verify | adminVerify2fa |
| POST | /auth/logout | adminLogout |
| GET | /auth/me | adminMe |
| POST | /auth/recovery-codes | regenerateRecoveryCodes |
| GET | /devices | adminListDevices (filters: status, owner, region, q; cursor) |
| POST | /devices | adminCreateDevice |
| GET | /devices/{deviceId} | adminGetDevice (with roster) |
| PATCH | /devices/{deviceId} | adminUpdateDevice |
| DELETE | /devices/{deviceId} | adminDecommissionDevice |
| POST | /devices/{deviceId}/disable | adminDisableDevice (super only) |
| POST | /devices/{deviceId}/enable | adminEnableDevice |
| POST | /devices/{deviceId}/transfer | adminTransferDevice (super only) |
| GET | /devices/{deviceId}/commands | adminDeviceCommands |
| GET | /devices/{deviceId}/diagnostics | adminDeviceDiagnostics |
| GET | /devices/{deviceId}/whitelist-queue | adminWhitelistQueue |
| POST | /devices/{deviceId}/whitelist/resync | adminResyncWhitelist (tech/super) |
| GET | /orders | adminListOrders |
| GET | /orders/{orderId} | adminGetOrder |
| POST | /orders/{orderId}/recheck | adminRecheckOrder |
| POST | /orders/{orderId}/refund | adminRefundOrder |
| GET | /refunds | adminListRefunds |
| GET | /subscriptions | adminListSubscriptions |

## PLANNED in OpenAPI but NOT yet deployed (mark as "planned / future batch")
Roster management (invite/add/remove/list roster, invitations accept), Notifications, Privacy (export/delete),
Profile (full GET/PATCH /me beyond biometrics), Admin Dashboard, Admin Users (customer mgmt), Admin Admins,
Admin Lookups, Admin Reports, Admin Audit, Admin Settings, Admin Feature Flags, Admin Notification Templates.
These exist in `docs/openapi/v1.yaml` but are NOT in the deployed `route:list`.
