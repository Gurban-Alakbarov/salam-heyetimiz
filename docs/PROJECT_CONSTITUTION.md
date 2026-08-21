# Salam Həyətimiz — Project Constitution

**Version:** 1.2 (transport amendment) — consolidation of the v1.1 set + the approved UMKa/Traccar/BLE transport pivot
**Status:** Active — final source of truth for implementation
**Date:** 2026-06-13 (v1.1) · 2026-06-14 (v1.2 transport amendment)
**Nature:** v1.1 **consolidated** the approved v1.1 specification set and introduced no new design. **v1.2 applies one approved design change** — the device-communication transport pivot in [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md), triggered by confirmed field hardware (GLONASSSoft **UMKa 310 v2L**, a telematics tracker, not the assumed CLIP GSM relay). Outside the GSM / Device-Communication rules + the listed risk items, this document remains a consolidation. Where it disagrees with a source document the source governs — except for the transport pivot, where `FINAL_TRANSPORT_DECISION.md` governs until the source docs are reconciled to v1.2.

> **v1.2 TRANSPORT AMENDMENT (2026-06-14).** Device communication moves from CLIP/SMS over a voice-gateway with an on-device caller-ID whitelist to a **hybrid**: **BLE** (local/in-person, primary), **Traccar** (remote opening + telemetry + command delivery, primary for remote — mandatory self-hosted infrastructure), **SMS** (emergency fallback only). The `DeviceDriver` interface and the open-command control plane are **unchanged**; only concrete transports change. **CRIT-01** (operator caller-ID rewrite) and **CRIT-03** (voice-gateway HA) are **retired**; **CRIT-06** is **resolved** (Traccar output read-back / BLE ack confirm actuation). See [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md), [DOCUMENT_CHANGE_PLAN.md](DOCUMENT_CHANGE_PLAN.md), [TRANSPORT_MIGRATION_CHANGELOG.md](TRANSPORT_MIGRATION_CHANGELOG.md).

## Source Materials (the frozen corpus)

This constitution is derived from, and subordinate to, the following approved v1.1 documents:

| Doc | Authority over |
|---|---|
| [TECHNICAL_SPECIFICATION.md](TECHNICAL_SPECIFICATION.md) | Scope, FR/NFR, flows, subscription & payment rules, security, deployment, phases |
| [DATABASE_ARCHITECTURE.md](DATABASE_ARCHITECTURE.md) | Physical schema, columns, constraints, indexes, partitioning, retention |
| [BACKEND_ARCHITECTURE.md](BACKEND_ARCHITECTURE.md) | Module layout, layering, patterns, middleware, queues, CI invariants |
| [UI_UX_SPECIFICATION.md](UI_UX_SPECIFICATION.md) | Mobile (S-/T-) and admin (A-) screens, states, copy |
| [LOCALIZATION_SPECIFICATION.md](LOCALIZATION_SPECIFICATION.md) | Locales, keys, formatting, i18n CI |
| [openapi/v1.yaml](openapi/v1.yaml) | The binding API contract (request/response/error shapes, operationIds) |
| [CHANGELOG.md](CHANGELOG.md) / [AUDIT_REPORT.md](AUDIT_REPORT.md) / [AUDIT_RESOLUTION_PLAN.md](AUDIT_RESOLUTION_PLAN.md) | Why each v1.1 decision exists (finding IDs CRIT-/HIGH-/MED-) |
| [IMPLEMENTATION_ROADMAP.md](IMPLEMENTATION_ROADMAP.md) | Phase sequencing, acceptance gates, workflow patterns |
| [futures/multi-tenancy-retrofit.md](futures/multi-tenancy-retrofit.md) | Deferred multi-tenant plan (NOT active work) |

## How to Use This Document

- Every rule below is **traceable** to a source section/finding. The citation in parentheses is authoritative.
- Rules use RFC-2119 language: **MUST**, **MUST NOT**, **SHOULD**, **MAY**.
- Violating a **MUST** / **MUST NOT** blocks merge. Several are enforced by CI (see §10.5).
- This document does not redesign. Items still awaiting product/legal sign-off are listed in **Appendix A** and are explicitly NOT invariants until signed.

---

## 1. Core Principles

1. **The spec is the contract.** The frozen v1.1 documents — above all `openapi/v1.yaml` — are the source of truth. Code conforms to docs; docs are not reverse-engineered from code. (Backend Arch §5.4; Roadmap W-5)
2. **No redesign without explicit request.** Implementation realises approved decisions. Architectural change requires a new, signed-off doc revision — never an ad-hoc code decision. (Audit Resolution Plan; this doc's mandate)
3. **Server is the authority; the client is advisory.** Every permission, price, and state transition is decided server-side. UI hints never substitute for server checks. (Tech Spec §15.3)
4. **Honesty over optimism.** We report what we actually know. An open reports `dispatched` ("Göndərildi") until actuation is observed; with Traccar output read-back or a BLE script ack it reports `opened` ("Açıldı"). (CRIT-06 — resolved under v1.2; see [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md))
5. **Idempotency everywhere externally invokable.** Open, order creation, renewal, refund, and bank callbacks are replay-safe. (Tech Spec §3.3; FR-OPEN; CRIT-07)
6. **Design in, don't bolt on, the foot-guns.** HA Redis, dual GSM gateway, key rotation, recovery codes, callback hardening are Phase-1 foundations, not Phase-4 polish. (Audit §6; CHANGELOG v1.1)
7. **Money and time are exact.** Money is integer minor units; timestamps are UTC. Never floats, never naive local time on storage. (DB Arch §0; Tech Spec §6.6)
8. **Privacy and audit are first-class.** PII anonymises on soft-delete; the audit log is append-only and immutable. (HIGH-12, HIGH-13)
9. **Modular monolith, bounded by domain.** Modules are isolated and communicate only through their public surface. (Backend Arch §1)
10. **Don't pre-pay for speculation.** Multi-tenancy and other unfunded futures are deferred with a written retrofit plan, not built now. (HIGH-17, Accept Risk)

---

## 2. Non-Negotiable Architectural Rules

### 2.1 Topology & Stack (locked)
- **R-ARCH-01** Backend stack is **Laravel 12 / PHP 8.4 / MariaDB 11 / Redis 7 / Horizon / Reverb**. Mobile is **Flutter**. Admin is **Laravel Blade + Bootstrap 5**. (Tech Spec §0; Backend Arch header)
- **R-ARCH-02** The backend is a **modular monolith** organised by bounded context under `app/Domain/<Module>/` — never by Laravel artifact type. New module ⇒ new `ModuleServiceProvider` auto-discovered by `DomainServiceProvider`; never edit `config/app.php` for it. (Backend Arch §1, §3.1)
- **R-ARCH-03** The folder structure, naming conventions, and migration batch order in `BACKEND_ARCHITECTURE.md` §3–4 and `DATABASE_ARCHITECTURE.md` §11 are mandatory and MUST NOT be reorganised.

### 2.2 Layering (locked)
- **R-ARCH-04** Request path is **Form Request (validation) → thin Controller → Domain Service → Model/Adapter → Resource**. Controllers MUST NOT touch Eloquent models directly or contain business logic. (Backend Arch §2, §6.2)
- **R-ARCH-05** **Database transactions are opened only inside Services** — never in Controllers, Jobs, or Listeners. Jobs/Actions/Listeners are thin wrappers around Service/Action logic. (Backend Arch §4, §5.2)
- **R-ARCH-06** Modules communicate **only** via (a) the other module's `Services` namespace, (b) domain events, (c) read models. A module MUST NOT reach into another module's models, jobs, or migrations. (Backend Arch §2)
- **R-ARCH-07** Cross-cutting concerns (`Audit`, `Notifications`) **react to events**; feature code MUST NOT call them directly. (Backend Arch §2, §8)
- **R-ARCH-08** Every external dependency (Kapital, SMS, voice/GSM, FCM, object storage) sits **behind an interface** bound in `IntegrationsServiceProvider`, with a `Fake*` test double. (Backend Arch §13)

### 2.3 Persistence & Availability (locked)
- **R-ARCH-09** Eloquent is the default. The Repository label is used **only** for the three cases in Backend Arch §5.1 (real boundary, shaped read model, hand-tuned hot SQL). No `XxxRepository` otherwise.
- **R-ARCH-10** Redis runs **HA (Sentinel: 1 primary + 2 replicas + 3 sentinels)** with the logical-DB split `0=cache, 1=queue, 2=locks, 3=broadcasting, 4=idempotency` and the stated `maxmemory-policy` per DB. A connection-level circuit breaker returns `503` for opens when Redis is unhealthy — it MUST NOT hang. (CRIT-05; Backend Arch §11.1.1)
- **R-ARCH-11** MariaDB is **primary + read replica**. The open-permission check and payment-callback processing go to **primary only**. (HIGH-06; DB Arch §13.4)
- **R-ARCH-12** **Two Reverb nodes** behind a sticky LB; WebSocket is strictly an optimisation — the mobile client MUST fall back to 1 s polling of `/v1/commands/{id}` and this fallback is a launch acceptance criterion. (HIGH-11; Tech Spec §6.4)

---

## 3. Domain Invariants

### 3.1 Identity
- **R-DOM-01** A user's canonical identity is **phone (E.164)**; `users.phone` is plain `UNIQUE`. `admin_users` is a **disjoint** identity space. (DB Arch §1.1, §1.2)
- **R-DOM-02** Phone is **reusable** after soft-delete: anonymisation replaces it with `deleted:<sha256(phone)>`, freeing the real number for a fresh signup under a new `user_id`. (HIGH-12)

### 3.2 Subscriptions
- **R-DOM-03** A subscription belongs to a **(user, device)** pair via `subscriptions.device_user_id` (UNIQUE — one logical sub per device_user). Renewals extend `ends_at`; historic terms are rows in `subscription_periods`. (Tech Spec §13.1; DB Arch §4.1–4.2)
- **R-DOM-04** Billing is **per user**: the owner pays their own `main` sub plus a separate `additional` sub per extra user. There is **no household/bundle discount**. (Tech Spec §1.2; CRIT-02)
- **R-DOM-05** The open-permission rule is: requesting user has an `active` subscription on this device AND is on its roster AND the device is not `disabled` AND cooldown not violated, **enforced in real time at request time for every open** (local and remote, both via the backend → Traccar path). (FR-OPEN-01) *(The v1.2 BLE time-boxed-entitlement exception is **withdrawn** per [FINAL_PHASE0_VERDICT.md](FINAL_PHASE0_VERDICT.md): BLE is deferred off the MVP critical path, so there is no offline local open. If BLE returns later as a convenience, a narrowly-scoped exception would be re-introduced for that path only.)*
- **R-DOM-06** Subscription states are exactly `pending_payment, active, expired, cancelled, refunded`. Device states are exactly `unassigned, active, suspended, disabled, decommissioned`. These enums MUST NOT be extended without a doc revision. (DB Arch §3.1, §4.1)
- **R-DOM-07** **Mixed-state is per-caller.** `Device.suspension_reason` is a per-caller derived field with values `none, subscription_expired, owner_sub_expired_others_active, device_disabled, device_suspended`. A device may be suspended for the owner while active for an invitee; the UI MUST render a different copy/CTA per caller and MUST NOT show a single shared device-wide status string. (CRIT-02; Tech Spec §13.9; UI/UX S-11)
- **R-DOM-08** Renewal math: renewed **before** expiry ⇒ `new_ends_at = old_ends_at + term_days`; renewed **after** expiry ⇒ `new_ends_at = now + term_days` (no retroactive credit). Grace window default 7 days. (Tech Spec §13.6)
- **R-DOM-09** Default locked values (held in `settings`, overridable only via admin/audited): device sale `13500`, sub_main `1200`, sub_additional `600` (placeholder), term_days `365`, grace_days `7`, user-device cooldown `5s`, device-global cooldown `2s`, reminder days `[30,15,7,1]`, diagnostics interval `6h`, offline threshold `24h`. (Tech Spec App. B; §13.3)

### 3.3 Roster, Devices, Ownership
- **R-DOM-10** `device_users` active-uniqueness is `(device_id, user_id)` where `status='active'`, implemented via the STORED generated `is_active` column + UNIQUE. Plan B (mirror table `device_users_active`) is the documented fallback **only if** the CI concurrency test (`ci:device-users-uniqueness`) fails on the deployed MariaDB. The decision is recorded in `docs/decisions/device-users-unique.md`. (HIGH-07; DB Arch §3.2)
- **R-DOM-11** Roster mutations are **append-logged** to `device_user_history`; revoked rows are never silently overwritten. (DB Arch §3.3)
- **R-DOM-12** **Owner self-delete is blocked** while any owned device has an active sub (owner's or invitee's): API returns `409 successor_required` with the blocking device list. Ownership transfer is admin-mediated (`adminTransferDevice`). Anonymisation runs only after a clean successor state. (HIGH-10; Tech Spec §3.7, §13.9)
- **R-DOM-13** A device-sale order MUST reference an existing `devices` row in `unassigned` state before the order is created. On `OrderPaid`, `DeviceAssigner` binds the payer as owner. (HIGH-09; Tech Spec §14.1.1)
- **R-DOM-14** Roster/whitelist capacity is **server-authoritative** against `device_models.whitelist_capacity` (NOT NULL, default 100). `devices.whitelist_capacity_used` is **DEPRECATED** — compute on read; do not write. Over-capacity invite ⇒ `409 roster_capacity_exceeded`. (HIGH-05; DB Arch §2.2, §3.1)

### 3.4 Data Integrity Conventions
- **R-DOM-15** Money is stored in **minor units (qəpik) as integers**; currency is `CHAR(3)` (`AZN`). Floats MUST NOT be used for money. (DB Arch §0.1)
- **R-DOM-16** All text is `utf8mb4_unicode_ci`. Timestamps are stored **UTC**; hot event tables use `TIMESTAMP(3)`. Human-time crons use `Asia/Baku`. (DB Arch §0; Tech Spec §6.6)
- **R-DOM-17** Soft-delete is used **only** on `users` and `devices`; all other tables use status enums. FKs default to `ON UPDATE CASCADE ON DELETE RESTRICT` unless the table block states otherwise. (DB Arch §0.2, §0.3)
- **R-DOM-18** Partitioned-from-day-one tables: `open_commands`, `audit_log`, `payment_logs`, `notifications`, `device_diagnostics` (RANGE by monthly `partition_key`, 12 forward partitions). (DB Arch §0.6)

### 3.5 Notifications
- **R-NOT** The notification-domain invariants **R-NOT-01 … R-NOT-22** are canonical; their normative text lives in [NOTIFICATIONS_INVARIANTS.md](notifications/NOTIFICATIONS_INVARIANTS.md) (Reconciliation §C). Load-bearing rules, restated:
  - **Identity:** per-`(user × channel)` `notifications` rows (no `channels_sent` bitmask); the channel bitmask is a **template-level** selector only. Recipient identity is **`user_id`**; multi-device push fan-out happens inside the push channel (no duplicate recipient rows). (R-NOT-01/02/04/08; DB Arch §7.3, §1.3)
  - **Idempotency:** dedupe `(user_id, dedupe_key, channel)`; `dedupe_key` encodes the business key. (R-NOT-05/06; Tech Spec §17.3)
  - **Payload:** FCM transport = `notification{title,body}` + `data:{type, notification_id, ids}`; **no** tokens/JWTs and no PII beyond the display title/body — sensitive detail fetched authenticated on tap. (R-NOT-17)
  - **State/retention:** `status ∈ {queued,sent,failed,read}`; `read_at` for `inapp`; real unread-inapp count in `/v1/me`; monthly partition + retention 12 mo inapp / 90 d others. (R-NOT-10/11/12/15/16; DB Arch §7.3)
  - **Tokens:** stored on `user_devices` (no new token table); FCM `UNREGISTERED` → soft `push_invalid=true`, reactivated on refresh. (R-NOT-09/14; Tech Spec §17.5)
  - **Admin:** admin-initiated sends require `notifications.view`/`notifications.send`, are audit-logged, and are `complex_manager`-scoped; single-language, no automatic translation. (R-NOT-19/20; Tech Spec §17.6)
  - **Scope:** MVP channels = **push + inapp** (sms/email future). Device path = **VL110C / Traccar / OpenCommand** only; barrier-opened fires on `OpenCommandCompleted[state=Opened]` (actuation-confirmed — Principle 4). **UMKa is out of scope** for notifications. (R-NOT-21/22; Reconciliation D10)

---

## 4. Security Invariants

- **R-SEC-01** **Transport** is TLS 1.2+ (prefer 1.3) with HSTS. (Tech Spec §3.4, §15.4)
- **R-SEC-02** **Mobile auth**: phone + 6-digit SMS OTP ⇒ RS256 JWT access token (15 min, carries `sub, kind, fp, kid`) + opaque refresh token (60 days, SHA-256 hashed, **rotated on each use**, device-fingerprint bound). (FR-AUTH-03; Tech Spec §15.2)
- **R-SEC-03** OTP rules: TTL 120 s; max 5 attempts per OTP; max 3 OTPs per phone per 10 min; 30/IP/hour. OTP codes are stored **hashed**, never in clear, never logged. (FR-AUTH-02; §15.6, §16.4)
- **R-SEC-04** **Biometric unlock is required before every `open` command** (Face ID / Touch ID / Android BiometricPrompt), with PIN fallback. (FR-AUTH-04) For the v1.2 **BLE local open**, this check is **app-enforced** (no server round-trip at open time — see R-DOM-05 BLE exception).
- **R-SEC-05** **Admin auth**: email + password (bcrypt cost 12) + TOTP (RFC 6238). Admin access token 30 min, carries `tfa_verified`. State-mutating admin routes require `tfa_verified=true`. (FR-AUTH-07; Backend Arch §6.5, §7.1)
- **R-SEC-06** **Admin recovery codes are MVP** (not P2): 8 single-use codes (10 hex chars), shown once, bcrypt-hashed in `admin_users.recovery_codes_hashes`; consumed slots set to `null`; regenerate replaces the whole set atomically. (CRIT-09; FR-AUTH-08)
- **R-SEC-07** Production invariant: **≥ 2 active super admins**, verified by a daily health check; recovery-by-peer is a documented two-person, audited workflow. (CRIT-09; Tech Spec §15.2.1)
- **R-SEC-08** JWTs carry a **`kid`**; admin signing keys are published at `GET /.well-known/jwks.json` (**admin host only**). Mobile **pins** and does not consume JWKS. Two `kid`s may be valid during rotation. (CRIT-04; Backend Arch §7.1.1)
- **R-SEC-09** **Mobile pins two SPKI public-key hashes** (primary + backup) per host, rotated **by app release** (not cert renewal), with CDN-side handshake-failure monitoring and a remote-flag escape-hatch host `api-recovery.salamhayetimiz.az`. (CRIT-08; Tech Spec §15.4, §15.8)
- **R-SEC-10** Every long-lived secret has an owner, cadence, and runbook under `docs/runbooks/key-rotation/`; each rotation writes `audit_log` `secret.rotated`. (CRIT-04; Tech Spec §15.5.1)
- **R-SEC-11** **Authorization is server-side via Policies/Gates** per Backend Arch §7.2–7.3. Every device action is checked against roster + role + subscription state. The client UI is advisory only. (Tech Spec §15.3)
- **R-SEC-12** **`audit_log` is immutable**: the runtime DB role has `INSERT, SELECT` only (no `UPDATE`/`DELETE`) on `audit_log` and `payment_logs`; enforced by committed GRANTs, the `ci:grants:audit-log-immutable` build check, and a daily production monitor. Audit rows are append-only and retained **5 years**. (HIGH-13; DB Arch §8.1)
- **R-SEC-13** **At-rest**: DB volume encryption + InnoDB tablespace encryption; application-layer `Crypt::encryptString` for `admin_users.totp_secret`, `card_tokens.bank_token`, payment raw responses; OTP/refresh tokens are hashed (one-way). (Tech Spec §15.6; DB Arch §0.1)
- **R-SEC-14** Validation is server-side via Form Requests (reject extra fields). Blade output is escaped by default; `{!! !!}` is reviewed. Admin panel uses CSRF; mobile uses bearer tokens. Only parameterised queries. CSP on admin. (Tech Spec §15.7)
- **R-SEC-15** **Redaction**: logs redact phone (last 4), PAN (last 4), tokens; OTP hashes/passwords are never logged. (Tech Spec §16.4)
- **R-SEC-16** Rate limits per OpenAPI/§9.5 are enforced at the gateway: OTP request 3/phone/10min + 30/IP/hour; OTP verify 10/phone/10min; open 12/user/min + 4/device/min; authed mobile 120/min/user; admin 600/min/admin. Two-phase admin login Step 2 is rate-limited per challenge and per email. (Tech Spec §9.5; MED-01)

---

## 5. Payment Invariants

- **R-PAY-01** Provider is **Kapital Bank e-Commerce hosted 3-D Secure**. Our backend **never** receives, stores, or logs full PAN / CVV / 3DS authentication values. Only bank order id, bank transaction id, masked PAN (last 4), brand, amounts, timestamps, and (future) card token may be stored. (Tech Spec §14.1, §14.6)
- **R-PAY-02** Order lifecycle states are exactly `pending, authorising, paid, failed, cancelled, refunded, partially_refunded, expired`; purposes `device_sale, sub_main, sub_additional, sub_renewal, bundle`. (DB Arch §5.1)
- **R-PAY-03** **Callback signature is verified over the RAW request bytes**, captured by `CaptureRawBody` middleware **before** JSON parsing. Nginx on this path uses `proxy_request_buffering on;` with no body-rewriting. (CRIT-07; Backend Arch §6.5)
- **R-PAY-04** Callback defence is layered: IP allowlist + HMAC-SHA256 over raw body + **always** call `getOrderStatus` and apply the bank's authoritative status — never trust the callback body for the state decision. This `getOrderStatus` cross-check is **non-negotiable** and documented in `ProcessPaymentCallbackJob`. (CRIT-07; Tech Spec §14.3; FR-PAY-04)
- **R-PAY-05** `status=PENDING` callbacks MUST NOT mutate `orders`; they persist a `payment_callbacks` row and schedule `RecheckOrderStatusJob` with backoff. (CRIT-07)
- **R-PAY-06** Callbacks are **deduplicated durably** via `payment_callbacks.payload_hash` (SHA-256 of canonical body) UNIQUE; a second arrival is idempotent (200). (CRIT-07; DB Arch §5.6)
- **R-PAY-07** `signature_invalid` metrics are partitioned by `ip_in_allowlist={true,false}` and alerted separately (attack vs. bank-side drift). (CRIT-07)
- **R-PAY-08** **Refund pro-rata is fixed math** (`RefundService::executeApprovedRefund`): `price_per_day_minor = floor(price_minor / term_days)`; **full** refund ⇒ sub `refunded`, `ends_at = now`, negative zero-length `subscription_periods`, whitelist removal queued; **partial** ⇒ `days_to_remove = floor(refund_amount / price_per_day_minor)`, `new_ends_at = max(now, current_ends_at - days_to_remove)`, negative `subscription_periods` row of `kind='refund'`; if `new_ends_at <= now` fall through to full-refund semantics. (HIGH-08; Tech Spec §14.5.1)
- **R-PAY-09** Refunds are **super-admin only**, pre-checked (order `paid`, within bank window, no prior full refund), idempotency-keyed, and audited. The `refunds` table tracks workflow intent; `payments` records the financial result. (FR-PAY-07; DB Arch §5.7)
- **R-PAY-10** `payment_logs` are written via an **allowlist** serializer (only whitelisted fields persist), app-layer encrypted (`request_redacted_encrypted` / `response_redacted_encrypted`), and scanned daily for PAN/CVV-like patterns (`PaymentLogsScannerJob`); enforced by `ci:payment-logs:no-pan`. Retention 5 years. (HIGH-15; DB Arch §5.5)
- **R-PAY-11** At most **one `authorising` order per subscription** at a time; the second is rejected `409 conflict`. (MED-02)
- **R-PAY-12** The payment **return URL `orderId` is a hint, not authority**. The app independently verifies the caller is the order's payer (`OrderPolicy::view` enforces `payer_user_id = auth id`) before rendering; on 403/404 it falls back to the most-recent in-flight order. (HIGH-16; UI/UX S-34)
- **R-PAY-13** An hourly `OrderReconciler` resolves `authorising` orders older than 30 min via `getOrderStatus` and backfills missing `payments` rows. (FR-PAY; Tech Spec §14.4)
- **R-PAY-14** Auto-renew stays **disabled** until Kapital tokenization is available; the `auto_renew` column and `card_tokens` exist but the cron skips. (Tech Spec §13.8; FR-SUB-07)

---

## 6. GSM / Device-Communication Invariants

> **v1.2 transport amendment applies to §6.** Hardware is the GLONASSSoft **UMKa 310 v2L** telematics tracker (Wialon IPS/Combine), not a CLIP GSM relay. Transports: **BLE** (local primary), **Traccar** (remote primary + telemetry + command), **SMS** (emergency fallback). See [FINAL_TRANSPORT_DECISION.md](FINAL_TRANSPORT_DECISION.md). The `DeviceDriver` interface + open-command control plane are unchanged; concrete drivers change. CRIT-01 & CRIT-03 **retired**; CRIT-06 **resolved**.

- **R-GSM-01** Device communication goes through a **modular driver layer** implementing the `DeviceDriver` interface (`open`, `whitelistAdd`, `whitelistRemove`, `diagnose`, `supports`) — **unchanged**. Driver types are **`traccar` (primary, remote + in-person) and `sms` (emergency fallback)**; **`ble` is reserved but deferred** off the MVP critical path ([FINAL_PHASE0_VERDICT.md](FINAL_PHASE0_VERDICT.md)); MQTT is future/optional. Adding a protocol MUST NOT require core changes. (Tech Spec §12; Backend Arch §14.6)
- **R-GSM-02** The driver is resolved by `DriverResolver::for(Device)` from `device.driver_type`. The per-operator caller-ID `OperatorFallbackPolicy` is **retired** (no CLIP). *(Supersedes the CLI-validation rule; CRIT-01 retired.)*
- **R-GSM-03** **Actuation confirmation.** A terminal `opened` state is permitted **only** when actuation is observable — for `traccar` (output-state read-back / command ack) and `ble` (on-device script ack). An unconfirmed dispatch terminates at `dispatched`. The API exposes `driver_confirms_actuation`; the UI renders "Açıldı" only when `true`, else "Göndərildi". *(CRIT-06 resolved — confirmation is now available via Traccar/BLE.)*
- **R-GSM-04** **Driver fallback** on a transient failure: retry **once** via `device_models.fallback_open_driver` (typically `sms` when the device is offline from Traccar). Both attempts persist as `open_command_attempts` rows; the parent `open_commands.state` is the last attempt's terminal state. Non-transient failures (`device_disabled, driver_unsupported, cooldown`) never fall back; whitelist/diagnostics never fall back. (HIGH-03; Tech Spec §12.7)
- **R-GSM-05** **Remote opens go through Traccar** (self-hosted, mandatory infra): backend → Traccar REST command → the device's live Wialon session → `cmdout.p`. If the device is offline from Traccar, the open falls to the **SMS** driver; it MUST NOT queue indefinitely. The voice-gateway / `VoiceGatewaySelector` / circuit-breaker model is **retired**. *(Supersedes the two-voice-gateway rule; CRIT-03 retired.)*
- **R-GSM-06** Open-command states are exactly `queued, dispatching, dispatched, opened, failed, expired`. Every open writes an `open_commands` row (the most write-heavy table). (DB Arch §6.1) — **unchanged.**
- **R-GSM-07** **Provisioning sync is an outbox**, never inline: an ownership/roster/credential change ⇒ `whitelist_changes` row (`pending`) ⇒ per-device serialised drain in `(device_id, priority ASC, seq ASC)` order, retried (max 5, backoff); `seq` is monotonic, independent of `created_at`. Under v1.2 the outbox carries **device-config / BLE-credential provisioning + Traccar authorisation sync**, not on-device caller-ID numbers. (HIGH-01, MED-09; DB Arch §6.3)
- **R-GSM-08** **Provisioning burst guard**: a user-initiated roster add is rejected `409 too_many_pending_changes` when ≥ 5 `pending` changes exist for the device; the UI shows a "Provisioning…" chip until `synced`. Admin-forced resyncs use `priority=10` (drain first); routine changes `priority=50`. (HIGH-01)
- **R-GSM-09** `expected_completion_ms` is **server-computed** from a rolling p90 of recent successful opens per driver/device (per-driver constant when < 10 samples). A fixed constant MUST NOT be returned. (HIGH-14; FR-OPEN-09) — **unchanged** (per-driver defaults re-baselined for traccar/ble/sms).
- **R-GSM-10** Telemetry + online/offline status arrive via **Traccar event-forward** (positions, I/O state, command results); a device with no telemetry for > 24 h is flagged offline (opens still attempted). Inbound SMS replies are HMAC-authenticated and correlated to an `open_commands` row **on the SMS fallback path only**. (Tech Spec §12.5, §12.8)
- **R-GSM-11** SIM lifecycle columns (`sim_credit_minor`, `sim_credit_checked_at`, `sim_status`) exist as **Phase-1 placeholders**; collectors are **Phase 2**. (HIGH-04; DB Arch §3.1)
- **R-GSM-12** Weekly **provisioning drift audit** (`devices:audit-whitelist`) is **Phase 2** (queries Traccar / the device, diffs against the platform view, repairs). (HIGH-02)
- **R-GSM-13** Performance targets (re-baselined for v1.2): open p95 ≤ **1 s** (BLE local) / ≤ **3 s** (Traccar remote, device online) / ≤ **30 s** (SMS fallback); throughput **5 RPS sustained / 50 RPS 1-min burst / 200 RPS 10-s peak**; above peak or when the remote transport is saturated, opens reject with `failure_reason=transport_capacity` and never queue indefinitely. (Tech Spec §3.1)

---

## 7. Localization Invariants

- **R-LOC-01** Supported locales are exactly **`az` (default & source of truth), `ru`, `en`** (BCP-47, region-agnostic). RTL is out of scope. (Localization §1)
- **R-LOC-02** **The API returns `error.code` + `error.message_key` (+ `details`), never a translated `message` as the contract.** Clients resolve keys from their own bundles. (Localization §11; principle "API returns keys, not strings")
- **R-LOC-03** Backend UI strings live in `lang/{az,ru,en}/*.php`; mobile mirrors them in `lib/l10n/intl_{locale}.arb` (compiled at build — **no OTA** in MVP); notification content lives in DB `notification_template_locales`; legal content in versioned `public/legal/{slug-vN}/{locale}.html`. (Localization §2–4)
- **R-LOC-04** Translation keys follow `<domain>.<area>[.<subkey>][.<variant>]` (snake_case). The fixed top-level prefixes (`auth, common, devices, errors, invitations, notifications, payments, privacy, profile, roster, subscriptions, validation, admin.*`) MUST NOT be invented ad-hoc. Keys are forever; substantive changes add a `.v2` and migrate over two release cycles. (Localization §5)
- **R-LOC-05** **`errors.*` keys map 1:1 to OpenAPI `error.code`; `validation.*` maps to Laravel rules.** Enforced by `ci:i18n:error-codes-cover`. (Localization §5.2, §10.2)
- **R-LOC-06** Placeholders are **named** (`{count}`, `{retry_after_seconds}`), part of the contract; ICU MessageFormat for plurals (Russian carries all four forms). (Localization §5.4–5.5)
- **R-LOC-07** Formatting: **24-hour time everywhere** (no AM/PM); dates AZ/RU `DD.MM.YYYY`, EN international `DD/MM/YYYY`; currency `₼` (U+20BC) with locale decimal/grouping; money rendered from integer `amount_minor` ÷ 100, never hand-formatted. (Localization §7)
- **R-LOC-08** Locale resolution — mobile: `users.preferred_language` → app override → device locale → `az`; admin: `admin_users.preferred_language` → `Accept-Language` (login only) → `az`. Server-rendered content (emails, SMS, receipts) uses the **recipient's** language. (Localization §6)
- **R-LOC-09** No string literals ≥ 3 chars in `.blade.php`/`.dart` outside `__()` / `AppLocalizations`; opt-out only via reviewed `// i18n-ignore`. The `i18n-sync` check (key parity, placeholder match, plural coverage) blocks the build. (Localization §5.7, §10)

---

## 8. API Invariants

- **R-API-01** **`docs/openapi/v1.yaml` (OpenAPI 3.1, v1.1.0) is the binding contract.** Every endpoint's request/response/error shape MUST match it. `php docs/openapi/validate.php` MUST pass in CI (`$ref` resolution, unique operationIds, declared tags). (Tech Spec §9.6; Backend Arch §15.1)
- **R-API-02** Route names, controllers, and API Resources map to OpenAPI by **operationId**: `Route::name('<operationId>')`, `App\Http\Api\V1\Resources\<Schema>Resource`. Resources are explicit (no `parent::toArray()`); the `ci:openapi:resources-match` golden-snapshot diff blocks the build. (Backend Arch §5.4, §6.1, §15.1)
- **R-API-03** Surfaces are separated: mobile/public under `/v1`, admin under `/admin/v1`, webhooks on their own routes. The two JWT guards (`mobile`, `admin`) are **disjoint** and not interchangeable. (Backend Arch §6.1; OpenAPI servers/security)
- **R-API-04** **`Idempotency-Key` is REQUIRED** on `POST /devices/{id}/open`, `POST /orders`, `POST /subscriptions/{id}/renew`, and `POST /admin/v1/orders/{id}/refund`. Replays return the cached response; a mismatched body for the same key returns `409 idempotency_mismatch`. Backed by the two-tier `IdempotencyHandler` (Redis + `idempotency_keys`). (OpenAPI; Backend Arch §11.3)
- **R-API-05** The error envelope is fixed: `{ error: { code, message_key, message, details, request_id } }`; validation uses the `ValidationErrorEnvelope`. Standard codes and HTTP statuses per Tech Spec Appendix A (incl. `subscription_required` 403, `cooldown` 429, `device_offline` 502, `successor_required`/`roster_capacity_exceeded`/`too_many_pending_changes` 409). (Tech Spec §9.1, App. A)
- **R-API-06** Lists use **cursor pagination** (`?cursor=&limit=`, response `page.next_cursor/has_more`). (Tech Spec §9.1; OpenAPI)
- **R-API-07** All timestamps are RFC 3339 UTC; phone fields match `^\+994\d{9}$`; locale enum is `[az, ru, en]`; money is integer minor with `currency=AZN`. (OpenAPI components)
- **R-API-08** Webhook routes (`/v1/payments/callback`, `/v1/sms/inbound`) carry no JWT; they authenticate by signature (`X-Kapital-Signature`, `X-Sms-Signature`) per R-PAY-03/04. (OpenAPI security; Backend Arch §6.5)
- **R-API-09** Versioning: breaking changes ship under a new path version. The planned **OpenAPI v1.2** (removes `message`, makes `message_key` required, restructures validation envelope) is a coordinated backend+mobile+admin release — **not** to be applied piecemeal. (Localization §11.6; Roadmap Phase 4)

---

## 9. Coding Standards

- **R-CODE-01** PHP follows **PSR-12**; static analysis **PHPStan/Larastan level 8** + Rector L1 MUST pass in CI. Flutter follows **Effective Dart** with `dart analyze` passing. (Tech Spec §3.5; Backend Arch §4)
- **R-CODE-02** **One class per file**, PSR-4. Naming per Backend Arch §4: `<Noun>Service`, imperative `Action` (`__invoke`/`handle`), `<Verb>Job`, past-tense events (no `Event` suffix), `<Domain><Reason>Exception`, `<Noun>Data` (readonly DTOs), `<Verb><Noun>Request`, `<Noun>Resource`, `<Model>Policy`. Tables snake_case plural; FKs `<singular>_id`; indexes `idx_*`, uniques `uq_*`, FKs `fk_*`.
- **R-CODE-03** DTOs are **readonly** and built via **`spatie/laravel-data`** (pinned). Services receive DTOs, never `Request` objects. Cross-module and job payloads use DTOs, not raw Eloquent models. (Backend Arch §5.3)
- **R-CODE-04** Domain exceptions extend `App\Exceptions\Contracts\DomainException` and implement `httpStatus()/errorCode()/messageKey()/details()`; the renderer maps them to the standard envelope. Adding an error is **one class** — never edit the Handler. (Backend Arch §6.6)
- **R-CODE-05** Controller methods are thin (a custom Larastan rule flags > ~25 lines). No business logic in controllers, jobs, or listeners. (Backend Arch §6.2, §5.2)
- **R-CODE-06** Listeners are **idempotent**, never throw through to the dispatcher in production, and never call another module's controller. (Backend Arch §8.2)
- **R-CODE-07** Config discipline: secrets in env/Vault (never DB, never `env()` in domain code — `config:cache` mandatory in prod); operational tunables in `settings` (audited); rollouts in `feature_flags`. Every `Settings::get()`/`FeatureFlag::active()` call carries a default. (Backend Arch §12)
- **R-CODE-08** Time is injected via `App\Support\Time\Clock` (never `now()`/`Carbon::now()` directly in domain logic) for testability. (Backend Arch §13.1)
- **R-CODE-09** Test coverage targets: **≥ 70 %** backend lines, **≥ 60 %** Flutter widget/unit. Feature/HTTP tests run against **MariaDB** (not SQLite in-memory). Drivers and payments are tested with `Fake*` doubles; integration tests hit sandboxes only. (Tech Spec §3.5, §20; Backend Arch §15)
- **R-CODE-10** Dependencies are **pinned by version** with a monthly upgrade review; Dependabot/Renovate enabled. (Tech Spec §3.5, §15.10)
- **R-CODE-11** Money arithmetic uses the `Money` value object; phone uses `PhoneNumber` (normalise to E.164 before storage); redaction uses `Redactor`. No ad-hoc string/number handling for these. (Backend Arch §3 Support; DB Arch §0.1)

---

## 10. Development Workflow

### 10.1 Phase Sequencing (locked)
- **R-WF-01** Work proceeds in the roadmap order: **Phase 0 (validation & procurement) → Phase 1A–1D (backend) → Phase 2A–2C (mobile) → Phase 3 (hardening + soft launch) → Phase 4 (scale)**. Phase exits require the stated acceptance criteria. (Roadmap §1, §4–7)
- **R-WF-02** **Phase 1 MUST NOT start** until Phase-0 acceptance gates **G1–G8** are green or each is owner-waived in writing, and the seven Fix-Now decisions (Appendix A) are signed. (Tech Spec §19; Audit §5, §7; Resolution Plan §4.3)
- **R-WF-03** Migrations are created in the batch order of DB Arch §11 (`00_system → 11_post_seed_constraints`); each batch only references prior-batch tables; all migrations are reversible (required for prod). (DB Arch §11; Tech Spec §18.4)

### 10.2 Definition of Done (per ticket)
- **R-WF-04** Code reviewed, tests green, CI green (lint + static analysis + invariant checks), deployed to staging, manually verified, and **docs updated**. (Tech Spec §20.8)
- **R-WF-05** **Spec ↔ code drift is not allowed.** Any PR that changes behaviour described in a v1.x doc MUST update that doc in the same PR (Roadmap W-5). "Update docs later" is prohibited.

### 10.3 Decision Records & Runbooks
- **R-WF-06** Non-trivial implementation decisions are recorded in `docs/decisions/` (e.g. `device-users-unique.md`, open-permission cache, voice-gateway topology). Operational procedures live in `docs/runbooks/` (key rotation, cert-pin, callback storm, failover, GRANT verification). (Roadmap W-2, W-7; Tech Spec §15.5.1)
- **R-WF-07** *(v1.2 — replaces CLI validation; CRIT-01 retired)* Phase-0 **transport validation** artefacts are committed under `docs/phase0/transport-validation.md` (Traccar→`cmdout.p` command proof + latency; BLE provisioning/security; SMS fallback; output read-back). See [BATCH_09B_SCOPE.md](BATCH_09B_SCOPE.md) §6.

### 10.4 Queues, Scheduler, Observability (operating rules)
- **R-WF-08** Async work uses the named Horizon queues (`high, default, device-comm, notifications, payments, reports, privacy`) with the documented tries/backoff; `device-comm` jobs are `WithoutOverlapping` per device. Dead-letter on `high`/`payments` pages on-call. (Backend Arch §9)
- **R-WF-09** Scheduled commands use the cadences in Backend Arch §10, all with `withoutOverlapping()` + `onOneServer()`. Human-time crons run in `Asia/Baku`. (Backend Arch §10; Tech Spec §6.6)
- **R-WF-10** Structured JSON logs carry `request_id, user_id, device_id, route, latency_ms, outcome, error_code`; the alert thresholds in Tech Spec §16.5 are the baseline. (Tech Spec §16)

### 10.5 CI Invariant Checks (build-blocking)
- **R-WF-11** The CI pipeline MUST run and pass all of: `ci:grants:audit-log-immutable` (HIGH-13), `ci:openapi:resources-match`, `ci:openapi:operationIds-unique` (`validate.php`), `ci:device-users-uniqueness` (HIGH-07), `ci:payment-logs:no-pan` (HIGH-15), `ci:i18n:error-codes-cover` (Localization §10.2). (Backend Arch §15.1)

### 10.6 Boundaries
- **R-WF-12** **Multi-tenancy MUST NOT be implemented** now. No `tenant_id` columns, no `TenantScope`. Revisit only on a concrete white-label trigger per `futures/multi-tenancy-retrofit.md`. (HIGH-17, Accept Risk)
- **R-WF-13** P2/P3 features (auto-renew, email receipts, PDF invoices, per-user access windows, MQTT driver, partial-refund UI, SIM-credit collectors, whitelist drift audit, TMS, pseudolocalization) MUST NOT be pulled forward into MVP without a doc revision. (Tech Spec §19; Roadmap §7)

---

## Appendix A — Items NOT Yet Locked (pending sign-off; NOT invariants)

These are explicitly **open** in the frozen docs and MUST be resolved by the responsible party before the dependent code is treated as final. They are listed here so they are not mistaken for settled invariants.

1. **Seven Fix-Now decisions** require written sign-off before Phase 1 (Resolution Plan §4.3): (a) ~~CLIP Phase-0 gate accepted as blocker~~ → **v1.2: replaced by the Traccar/BLE/SMS transport-validation gate** (CRIT-01 retired); (b) §13.9 edge-case table approved by product; (c) NFR §3.1 throughput revision; (d) terminal copy "Göndərildi"/"Açıldı" driven by `driver_confirms_actuation`; (e) open-permission cache removed; (f) phone reusable post-anonymisation; (g) `tenant_id` not added (retrofit filed).
2. **Phase-0 open items** (Tech Spec §0): exact additional-user price, **device model/firmware confirmed = GLONASSSoft UMKa 310 v2L (v1.2)**, SMS-OTP provider, Kapital tokenization availability, VAT/receipt obligation (MED-12 — counsel review), data residency. **New v1.2 transport items:** Traccar→`cmdout.p` command proof + latency; BLE provisioning/security model; SMS fallback syntax/cost.
3. **Documentation reconciliation:** `admin_users.preferred_language` is required by `LOCALIZATION_SPECIFICATION.md` (§6.2, Appendix E) but is not yet present in `DATABASE_ARCHITECTURE.md` §1.2. The Localization spec flags this as a "follow-up DB revision." This MUST be reconciled in the schema before the admin locale feature is implemented — as a documentation correction, not a redesign.
4. **Schema questions pending** (DB Arch §15) and **open architectural questions** (Backend Arch §16) — e.g. Reverb-vs-Soketi, JWT library choice, owner-transfer subscription mechanics — remain as documented; implementation defaults to the stated current plan unless a decision record changes it.

---

*End of Project Constitution v1.1 — a consolidation of approved v1.1 decisions. No new design introduced.*
