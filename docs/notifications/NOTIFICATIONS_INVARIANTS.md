# Notifications — Invariants (proposed `R-NOT-*`)

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT. This is the **drafting ground** for the notification invariants that will later be consolidated into `PROJECT_CONSTITUTION.md` as `R-NOT-*`. No canonical document is modified by this file.
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) · Constitution §3 (rule-code system, RFC-2119) · Tech Spec §17 · DB Arch §7.
**Convention:** RFC-2119 (MUST / MUST NOT / SHOULD / MAY). Each rule cites its source. This document is the **single canonical home** for these invariants; other module docs cite `R-NOT-*` and MUST NOT restate them.

---

## Identity & Record Model

- **R-NOT-01** A notification record is **per-(user × channel)**: one `notifications` row per recipient per delivery channel. There is **no** single-row multi-channel record and **no** `channels_sent` bitmask. *(DB Arch §7.3; Reconciliation D1/B1.)*
- **R-NOT-02** The **recipient identity is `user_id`** — never a device, never a `device_user_id`. A user appearing on multiple devices or roster rows is exactly **one** recipient. *(Reconciliation D6/B1.)*
- **R-NOT-03** Human-facing content and navigation data (title, body, deep-link, entity ids) live in the row's **`payload` JSON**. Category and default channel selection live on the **template**, not on the notification row. *(DB Arch §7.1/§7.3; Reconciliation D1/D12.)*
- **R-NOT-04** The `channel` bitmask (`config/domain/notifications.php`; `notification_templates.default_channels_mask`, push=1/sms=2/inapp=4/email=8) is a **template-level channel selector** that fans out into per-channel rows. It MUST NOT be repurposed as a per-row "sent" mask, and there is **no second channel-mask at any other level** — in particular **admin campaigns carry no channel mask**; at MVP they fan out to the fixed **push + inapp** channels. *(Reconciliation D1, Revision-2.)*

## Idempotency & Deduplication

- **R-NOT-05** Fan-out MUST be idempotent. The physical uniqueness rule is **`(user_id, dedupe_key, channel)`** (`uq_notifications_dedupe`). *(DB Arch §7.3; Tech Spec §17.3 reworded per B5.)*
- **R-NOT-06** `dedupe_key` MUST encode the business/source key so retries, worker restarts, queue re-drives, and FCM retries never double-create. Canonical keys: `visitor_opened:{open_command_id}`, `sub_expiring:{subscription_id}:{cycle}`, `sub_expired:{subscription_id}`, `campaign:{campaign_id}:{user_id}`. *(Reconciliation D2.)*
- **R-NOT-07** For a barrier open, the same `OpenCommand` MUST yield at most one notification per (user, channel). *(Reconciliation D2/D14.)*

## Multi-Device Delivery

- **R-NOT-08** Push delivery fans out **inside the push channel** to every **active** device of the recipient: `user_devices` where `revoked_at IS NULL AND push_invalid = false AND push_token IS NOT NULL`. This multi-device fan-out MUST NOT create additional recipient-level `notifications` rows (one push row per user; delivery iterates devices). *(DB Arch §1.3; Reconciliation D6/D11.)*
- **R-NOT-09** No dedicated push-token table is introduced; the token lives on `user_devices` (owned by Auth/Users). Notifications only **reads** it and sets `push_invalid`. *(DB Arch §1.3; Reconciliation D11.)*

## Delivery & Read States

- **R-NOT-10** Per-channel delivery state is the canonical `status ENUM('queued','sent','failed','read')` with `failure_reason` + `sent_at`. *(DB Arch §7.3.)*
- **R-NOT-11** `read_at` applies to the **`inapp`** channel only; marking read is a recipient-only action (`NotificationPolicy::markRead`). *(DB Arch §7.3; Backend Arch §14.9.)*
- **R-NOT-12** The in-app **unread count** is a real query over unread `inapp` rows (`idx_notifications_user_channel_status`). `GET /v1/me.unread_notifications_count` MUST return this real value, not a literal. *(Reconciliation D13.)*

## Failure, Retry & Token Invalidation

- **R-NOT-13** Delivery runs on the Horizon **`notifications`** queue (async); it MUST NOT run inside a request. Horizon retry/timeout/backoff govern transient failures. *(Backend Arch; Constitution R-ARCH-05; `config/horizon.php`.)*
- **R-NOT-14** An FCM `UNREGISTERED`/invalid-token response MUST **soft-invalidate** the token: `user_devices.push_invalid = true` (never a hard delete here). A subsequent token refresh MUST clear it. *(Tech Spec §17.5; Reconciliation D11/E resolutions.)*

## Retention & Partitioning

- **R-NOT-15** `notifications` MUST remain **monthly range-partitioned** on `partition_key (YYYYMM)`; every write sets `partition_key`. *(DB Arch §7.3; Reconciliation D9/B1.)*
- **R-NOT-16** Retention is canonical: **12 months for `inapp`**, **90 days for non-inapp** channels. These MUST NOT be silently altered. *(DB Arch §7.3; Reconciliation D9.)*

## Security & Permissions

- **R-NOT-17 (transport & content boundary — revised)** A notification has **two payloads** (see [ARCHITECTURE §5.1](NOTIFICATIONS_ARCHITECTURE.md)): the persisted `notifications.payload` (R-NOT-03) and the **FCM transport message**. The FCM message is a **`notification{title,body}` + `data: { type, notification_id, ids }`** hybrid — the **display content** (title/body) and the minimal routing ids (`notification_id` = our `notifications` row id; `ids` = target entity ids) ARE permitted (the title/body are exactly what the user sees on the lock screen/tray). **Prohibited in either payload:** auth tokens, JWTs, full personal records, and any PII beyond what the display title/body legitimately show. Title/body MUST be authored **PII-minimal** (e.g. *"Qonağınız qapını açdı"* — never a visitor's name/email/phone). Authoritative or sensitive detail is **fetched authenticated on tap**, never carried in the message. *(Rationale: FCM/APNs relay messages through third-party servers in clear — display content is acceptable, secrets/sensitive PII are not. Reconciliation Revision-1; `payload_max_bytes=4096` guard.)*
- **R-NOT-18** `push_token` is secret: `$hidden` on the model, never returned by an API. *(DB Arch §1.3; R-SEC-15.)*
- **R-NOT-19** Admin-initiated sends require **`notifications.send`**; listing/preview requires **`notifications.view`**. `complex_manager` admins are scoped to their own complex's residents. Every admin send is **audit-logged**. *(Reconciliation D6; ADMIN_PERMISSION_MATRIX; Audit module.)*

## Localization

- **R-NOT-20** Business/system notifications are localized **per recipient** via `notification_template_locales` (az/ru/en), resolved at fan-out from the user's `preferred_language`. Admin free-text campaigns carry **one** chosen language and are delivered as-is. **No automatic translation** is ever performed. *(Tech Spec §17.2; Reconciliation D3/D7/B6.)*

## Scope

- **R-NOT-21** The notification domain covers the **VL110C / Traccar / OpenCommand** device path only. **UMKa is out of scope**: no UMKa event, listener, webhook, migration, abstraction, or future-support placeholder. *(Reconciliation D10.)*
- **R-NOT-22** MVP delivery channels are **push + inapp**. **SMS + email are future**; enabling them MUST require only new per-channel rows, never a change to R-NOT-01..R-NOT-08. *(Reconciliation D4/B7.)*

---

**Consolidation note:** on approval, R-NOT-01..R-NOT-22 are proposed for inclusion in `PROJECT_CONSTITUTION.md` (new §3.x "Notifications") as a canonical-edit batch (see [RECONCILIATION §C](NOTIFICATIONS_RECONCILIATION.md)). Until then they are DRAFT and non-binding.
