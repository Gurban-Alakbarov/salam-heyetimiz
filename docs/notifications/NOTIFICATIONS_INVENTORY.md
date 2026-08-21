# Notifications — Inventory (type catalogue)

**Version:** 0.1 (DRAFT — pending documentation-set sign-off)
**Status:** DRAFT. Reconciles the live notification types with the canonical **Tech Spec §17.2 template catalogue**. No canonical doc modified.
**Date:** 2026-08-11
**Depends on:** [NOTIFICATIONS_RECONCILIATION.md](NOTIFICATIONS_RECONCILIATION.md) · Tech Spec §17.2 · Backend Arch event matrix (§14.9) · [INVARIANTS](NOTIFICATIONS_INVARIANTS.md) R-NOT-05/06/20.
**Rule:** only types backed by a **real, already-emitted** event/data are MVP. No invented business events (R-NOT-21; UMKa excluded).

---

## 1. MVP — first notification (ships alone, end-to-end first)

| Field | Value |
|---|---|
| **Type** | `visitor_link_used` (visitor barrier opened) |
| **Trigger** | `OpenCommandCompleted` where **`state = Opened`** AND **`source = Visitor`** |
| **Why reliable** | VL110C confirms actuation (`TraccarDriver::awaitCommandResult` → 0x21 `Success!`) **before** this event; `state=Opened` = observed actuation (Constitution Principle 4). **Never** fire on `dispatched`. |
| **Recipient** | owning resident, resolved from the command's `metadata.visitor_link_id` (same signal as the existing `IncrementVisitorUsageOnOpenCompleted` listener) |
| **Template key** | `device.opened` (canonical §17.2, "optional/configurable") — visitor variant copy |
| **Channels (MVP)** | push + inapp |
| **Category** | `operational` |
| **dedupe_key** | `visitor_opened:{open_command_id}` (R-NOT-06/07) |
| **Deep link** | `data.ids: { visitor_link_id, device_id }` (type `visitor_link_used`) → device detail / visitor screen |
| **Phase** | **MVP #1** |

> **LOCKED (Revision-3):** `type = visitor_link_used`; `template_key = device.opened` (canonical §17.2 — **no** separate `visitor.*` namespace for MVP); visitor-specific copy is provided via the applicable locale/template content; trigger stays `OpenCommandCompleted` with `state = Opened` and `source = Visitor`. No change to the VL110C / Traccar / OpenCommand event architecture.

## 2. MVP — second wave (after #1 is proven)

| Type | Trigger event (real) | Template key | Channels | Category | dedupe_key | Deep link |
|---|---|---|---|---|---|---|
| `subscription_expiring` | `SubscriptionExpiringSoon` (reminder cron `subscriptions:send-renewal-reminders`) | `subscription.expiring_{30,15,7,1}d` | push+inapp (7d/1d add sms **future**) | `billing` | `sub_expiring:{subscription_id}:{cycle}` | active subscriptions screen |
| `subscription_expired` | `SubscriptionExpired` (expiry sweep) | `subscription.expired` | push+inapp | `billing` | `sub_expired:{subscription_id}` | active subscriptions |
| `subscription_activated` | `SubscriptionActivated` (initial/renewal) | `subscription.renewed`/receipt | push+inapp | `billing` | `sub_activated:{subscription_id}:{period_id}` | active subscriptions |
| `subscription_renewed` | `SubscriptionRenewed` | `subscription.renewed` | push+inapp | `billing` | `sub_renewed:{subscription_id}:{period_id}` | active subscriptions |
| `system_announcement` (admin) | Admin campaign (no event) | `system.admin_campaign` (reserved) | push+inapp | `operational` (forced) | `campaign:{campaign_id}:{user_id}` | NotificationScreen |

All subscription events exist today (Backend Arch event matrix; Subscriptions module). Admin campaign details → [ADMIN_SPEC](NOTIFICATIONS_ADMIN_SPEC.md).

## 3. Visitor link expired *(later phase — needs a new sweeper)*

- Expiry today is an **on-read predicate** (`VisitorLink::isExpired`); there is **no event, no job, no write** (Reconciliation Event-3 audit).
- To notify, a **scheduled sweeper** (mirroring the existing `ExpireStaleOpenCommandsJob` pattern, on the `default`/`notifications` queue) selects links crossing `expires_at` and produces one notification.
- **One-time delivery** guaranteed by `dedupe_key = visitor_expired:{visitor_link_id}` (R-NOT-05) — the sweeper may re-select safely.
- **Phase:** post-MVP. Value is modest ("your visitor link expired unused"); not a blocker.

## 4. Deferred (catalogued, NOT in MVP)

| Type | Canonical | Why deferred |
|---|---|---|
| `device.offline` | §17.2 (push, owner, 24h since last diag) | **No reliable emitted offline event** today (only `last_online_at` / `consecutive_offline_diagnostics` fields). E4/approved: out of MVP; **do not invent** an offline event. |
| `device.online` | not canonical | no event; out of scope |
| `device.open_failed` | §17.2 / `OpenCommandFailed → Notifications (push)` | valid future item; MVP focuses on the success path; add after #1 |
| `auth.welcome`, `device.invite`, `device.user_added/removed`, `payment.*` | §17.2 event matrix | real events exist; sequence **after** the visitor + subscription waves; not MVP-critical |

## 5. Channel policy per category (Tech Spec §17.1 + D4/B7)

- MVP channels = **push + inapp** for every live type above.
- `sms` appears in canonical §17.2 for high-importance billing (`expiring_7d/1d`, `expired`) — **future**; the template `default_channels_mask` already encodes it, so enabling sms later adds rows only (R-NOT-22).
- `email` = future (P2).
- `security`/`billing` categories are **non-mutable** (forced); `marketing` is the only mutable category (DB Arch §7.1 `is_user_mutable`) — preferences UI deferred (E6).

## 6. Localization (R-NOT-20)

- **Business/system types** (§1–§2 except admin): bodies in `notification_template_locales` (az/ru/en); resolved at fan-out from the recipient's `preferred_language`; fallback to default locale. Keys under `lang/{az,ru,en}/notifications.php` (Localization Spec).
- **Admin campaigns:** single chosen language, free-text, delivered as-is; **no auto-translation** (D7).

## 7. Canonical edits this inventory implies (post-approval)
`TECHNICAL_SPECIFICATION.md §17.2`: annotate the catalogue as **templated business events**; add a pointer that admin free-text is a **non-templated** path (§17.6); mark `device.offline` and sms rows as future/deferred. (Tracked in [RECONCILIATION §C](NOTIFICATIONS_RECONCILIATION.md).)
