# Notifications — Admin-Initiated Notifications (draft §17.6)

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT. Drafting ground for a **new** canonical section **Tech Spec §17.6**. No canonical doc modified by this file.
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) (D5–D8, B2–B4) · [DATABASE_PLAN](NOTIFICATIONS_DATABASE_PLAN.md) (`notification_campaigns`) · [INVARIANTS](NOTIFICATIONS_INVARIANTS.md) R-NOT-02/17/19 · `AdminResidentController` (residents directory) · admin `Permission` enum / RBAC · complex entity.

---

## 1. Capability

Admins compose and send a **`system`** notification to an audience of residents (e.g. planned-maintenance announcements). It reuses the **single** `NotificationDispatcher` / `notifications` queue / inbox — **not** a parallel push system (R-NOT / Architecture §4). The message reaches recipients as **push + inapp** — the **fixed** MVP channels; admin campaigns carry **no** channel mask (D1/R-NOT-04) — and remains in each recipient's inbox.

## 2. Audience model (D6)

`notification_campaigns.audience_scope` ∈:

| Scope | `audience_filter` | Resolution |
|---|---|---|
| `all_users` | — | all active residents (distinct `user_id`) |
| `user_ids` | `{user_ids:[…]}` | explicit hand-picked recipients |
| `filter` | `{q, complex_id, role, subscription_status}` | everyone matching the residents-directory filter (resolved server-side) |
| `complex` | `{complex_id}` | all residents of a complex |

- **Recipient resolution MUST dedupe to distinct `user_id`** (R-NOT-02). The residents directory (`GET /admin/v1/residents`) returns rows per `device_user_id`; a user on two devices is **one** recipient.
- Resolution reads residents via the existing directory/query (reuse `q` = name/email/phone search + `complex_id`/`role`/`subscription_status` filters + cursor). No new user-management architecture.
- **`complex_manager` scope:** a complex-scoped admin's audience is **intersected** with their own complex; they cannot target outside it (mirrors `AdminResidentController` scoping).

### "Select all filtered" and count
- Explicit `user_ids` = client holds the set; count = set size.
- `filter` / `all_users` / `complex` cannot be enumerated client-side under cursor pagination → the **filter itself is the audience**; the backend resolves + counts.
- The recipient **count shown before confirmation MUST be server-resolved** (distinct `user_id`) — a `count_only`/preview resolution of the audience (E3; exact endpoint shape decided at API time). This count is authoritative for the confirmation step.

## 3. Permissions (D6 / B4)

- **`notifications.view`** — list campaigns, preview audience/count.
- **`notifications.send`** — create + dispatch.
- **Roles (RBAC decision D1 — "Variant B"):** `send` = super_admin + Operator (global) + Complex Manager (own-complex scope); `view` also Support; Technical / Finance none. (See `RBAC_PERMISSION_MATRIX.md`.)
- Added to the admin `Permission` enum + `ADMIN_PERMISSION_MATRIX` (canonical edit, post-approval). Frontend gates with `PermissionGate` + `PERM`.
- **Audit:** every send emits an `AuditableEvent` (audit module) capturing admin user, campaign, scope, `created_at`, `sent_at`, result (R-NOT-19).

## 4. Language (D7 / B6)

- Admin selects **one** language ∈ {az, en, ru}; enters **one** title + body; delivered as-is to the whole audience regardless of each user's app language.
- **No automatic translation** — ever.
- **Future (architecturally open, not MVP):** a `translations` JSON on the campaign resolved per recipient `preferred_language` at fan-out. This is additive: per-recipient `notifications.payload` already stores the resolved copy, so no identity-model change (R-NOT-20/22).

## 5. Mandatory send confirmation (D8)

- The compose form's **Send** action opens a **confirmation dialog** (reuse the existing admin `ConfirmDialog`); it shows: **recipient count** (server-resolved), **type**, **title**, **body**, **audience/scope**, **selected language**, and the warning *"This notification will be sent immediately and cannot be recalled."* Actions: **Cancel** / **Send now**.
- The dispatch **POST fires only after** confirmation. `notification_campaigns.confirmed_at` records it. This prevents accidental mass sends.

## 6. Campaign lifecycle & fan-out

```
draft ──(confirm+send)──► queued ──► sending ──► sent | failed
```
- On send: resolve audience → distinct `user_id` set → set `total_recipients` → **chunked** queued jobs (e.g. 500 recipients/chunk) each: bulk-insert per-channel `notifications` rows (`template_key='system.admin_campaign'`, `campaign_id`, rendered payload) + push fan-out across each recipient's active `user_devices`.
- Idempotency `dedupe_key = campaign:{campaign_id}:{user_id}` per channel (R-NOT-05/06) → chunk retries never double-send.
- `sent_count` / `failed_count` rolled up from per-channel delivery outcomes.
- The campaign **title/body are the notification's display content** (FCM `notification` block + inbox — [ARCHITECTURE §5.1](NOTIFICATIONS_ARCHITECTURE.md)) and MUST be **PII-minimal** (R-NOT-17): no third-party personal data, no secrets. Delivery is always **push + inapp** (no campaign channel mask).

### All-users performance (broadcast vs per-user)
- **Decision:** **chunked per-user fan-out** into `notifications` rows. Keeps the inbox model uniform (`WHERE user_id = me`), enabling read-state + unread count per recipient. The `notification_campaigns` row holds the campaign-level stats.
- At very large scale (100k+ users) a broadcast + per-user read-marker model would reduce row count — recorded as a **future optimization**, not MVP (residential scale is thousands).

## 7. Admin UI (reuse existing admin-ui patterns)

A new **"Send Notification"** area (React admin-ui). MVP:
- **Compose:** title, body, `type=system`, language, audience picker.
- **Recipient picker:** the **residents table pattern reused** — `useResidents` (search `q`, complex filter, cursor) + row **checkboxes** + a persistent **selected chips** bar (remove per chip) + **"select all filtered"** (→ filter-audience) + a **server-resolved count** badge.
- **Send → ConfirmDialog** (§5) → dispatch.
- **List:** campaigns with status, `total_recipients`, `sent_count`, `failed_count`, created by/at, sent at.
- Gated by `PermissionGate` (`notifications.view` / `notifications.send`).

**Future phase (not MVP):** scheduled send, preview render, per-category targeting, templates for admin copy.

## 8. Scheduling (explicitly future)

MVP = **Send now**, production-ready. Scheduled send (e.g. "at 22:30") is **future**: a `scheduled_at` on the campaign + a scheduler dispatching into the same `NotificationDispatcher`. Not required for MVP.

## 8a. API endpoints (mirror of `openapi/v1.yaml`)

| Method · Path | operationId | Permission |
|---|---|---|
| `GET /admin/notifications` | `adminListNotificationCampaigns` | `notifications.view` |
| `POST /admin/notifications/audience/preview` | `adminPreviewNotificationAudience` | `notifications.view` |
| `POST /admin/notifications` (Idempotency-Key; `confirmed=true`) | `adminSendNotification` | `notifications.send` |
| `GET /admin/notifications/{campaignId}` | `adminGetNotificationCampaign` | `notifications.view` |

Send returns `202` (queued fan-out). Errors: `403` (no permission / out-of-scope complex) · `409` (missing confirmation) · `422` (validation). `complex_manager` is server-scoped to its own complex on every operation; recipients dedupe by `user_id`. Request/response schemas: `NotificationCampaign` · `NotificationCampaignCreate` · `AudienceSpec` · `AudiencePreview`. The mobile push-token lifecycle uses `PUT` / `DELETE /v1/notifications/push-token` (see [MOBILE_FLOW](NOTIFICATIONS_MOBILE_FLOW.md)).

## 9. Canonical edits this spec will require (post-approval)
- `TECHNICAL_SPECIFICATION.md`: **new §17.6 Admin-Initiated Notifications** (this content).
- `ADMIN_PERMISSION_MATRIX.md`: `notifications.view`, `notifications.send`.
- `openapi/v1.yaml`: admin campaign endpoints (+ audience preview/count).
- `UI_UX_SPECIFICATION.md`: a new admin **A-** screen for "Send Notification".
- `DATABASE_ARCHITECTURE.md §7.5`: `notification_campaigns` (see [DATABASE_PLAN](NOTIFICATIONS_DATABASE_PLAN.md)).
(Tracked in [RECONCILIATION §C](NOTIFICATIONS_RECONCILIATION.md).)
